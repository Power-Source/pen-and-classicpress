<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_npc_url( $scope, $page_id, $id = 0 ) {
	$url = 'pspp_universe' === $scope->post_type ? ps_pen_paper_universe_url( $scope->ID, $page_id )
		: ( $page_id ? ps_pen_paper_game_url( $page_id, $scope->ID ) : ps_pen_paper_admin_url( $scope->ID ) );
	return add_query_arg( $id ? array( 'pspp_npc' => $id ) : array( 'pspp_npcs' => 1 ), $url ) . ( 'pspp_universe' === $scope->post_type ? '#pspp-npcs' : '#pspp-folio' );
}

function ps_pen_paper_handle_npc_action( $operation ) {
	$scope_id = ps_pen_paper_posted_id( 'campaign_id' );
	$page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
	check_admin_referer( 'pspp_campaign_' . $scope_id );
	$scope = ps_pen_paper_get_character_scope( $scope_id );
	if ( ! ps_pen_paper_can_read_npcs( $scope ) || ! ps_pen_paper_can_manage_character_scope( $scope ) ||
		( isset( $_POST['pspp_frontend_page'] ) && ! ps_pen_paper_frontend_page( $page_id ) ) ) {
		ps_pen_paper_fail( __( 'Diese Figurenverwaltung ist für dich nicht verfügbar.', 'ps-pen-paper' ) );
	}
	$id = ps_pen_paper_posted_id( 'npc_id' );
	$revision = ps_pen_paper_posted_text( 'npc_revision' );
	if ( 'save_npc' === $operation ) {
		if ( 'complete' !== ps_pen_paper_posted_text( 'npc_complete' ) ) { ps_pen_paper_fail( __( 'Das Figurenformular wurde nicht vollständig übertragen.', 'ps-pen-paper' ) ); }
		$path = null;
		if ( isset( $_FILES['npc_image'] ) ) {
			$file = $_FILES['npc_image'];
			if ( ! is_array( $file ) || ! isset( $file['error'] ) ||
				( UPLOAD_ERR_NO_FILE !== $file['error'] && ( UPLOAD_ERR_OK !== $file['error'] || ! isset( $file['tmp_name'] ) || ! is_string( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) ) ) {
				ps_pen_paper_fail( __( 'Das Figurenbild wurde nicht vollständig hochgeladen.', 'ps-pen-paper' ) );
			}
			if ( UPLOAD_ERR_OK === $file['error'] ) { $path = $file['tmp_name']; }
		}
		$result = ps_pen_paper_save_npc( $scope, $id, isset( $_POST['npc'] ) ? wp_unslash( $_POST['npc'] ) : null, $revision, $path );
		if ( ! is_wp_error( $result ) ) { $id = $result; }
	} elseif ( 'approve_npc' === $operation ) {
		$result = ps_pen_paper_review_npc( $scope, $id, $revision, ps_pen_paper_posted_text( 'npc_confirmation' ) );
	} else {
		$result = ps_pen_paper_transfer_npc( $scope, $id, $revision, ps_pen_paper_posted_text( 'npc_confirmation' ), 'offer_npc' === $operation );
		if ( ! is_wp_error( $result ) && 'copy_npc' === $operation ) { $id = $result; }
	}
	if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
	wp_safe_redirect( ps_pen_paper_npc_url( $scope, $page_id, $id ) );
	exit;
}

function ps_pen_paper_render_npc_card( $scope, $state, $campaign = null ) {
	if ( ! ps_pen_paper_can_read_npcs( $scope, $campaign ) || ( 'proposed' === $state['status'] && ! ps_pen_paper_can_manage_universe( $scope ) ) ) { return; }
	$npc = $state['npc'];
	?>
	<article class="pspp-npc-card">
		<h4><?php echo esc_html( $npc['name'] . ' · ' . ps_pen_paper_npc_kinds()[ $npc['kind'] ] ); ?></h4>
		<p><?php echo esc_html( ps_pen_paper_npc_statuses()[ $state['status'] ] ); ?></p>
		<p class="pspp-multiline"><?php echo esc_html( $npc['description'] ); ?></p>
		<?php if ( $npc['asset_id'] ) :
			$image = ps_pen_paper_private_image_data( $scope, $npc['asset_id'] );
			if ( is_wp_error( $image ) ) : ?><p role="alert"><?php echo esc_html( $image->get_error_message() ); ?></p>
			<?php else : ?><img src="<?php echo esc_attr( $image ); ?>" alt="<?php echo esc_attr( $npc['name'] ); ?>" loading="lazy"><?php endif;
		endif; ?>
		<?php foreach ( array( 'properties' => __( 'Eigenschaften', 'ps-pen-paper' ), 'values' => __( 'Werte', 'ps-pen-paper' ) ) as $key => $label ) : if ( ! $npc[ $key ] ) { continue; } ?>
			<h5><?php echo esc_html( $label ); ?></h5><dl><?php foreach ( $npc[ $key ] as $field ) : ?><dt><?php echo esc_html( $field['label'] ); ?></dt><dd><?php echo esc_html( $field['value'] ); ?></dd><?php endforeach; ?></dl>
		<?php endforeach; ?>
		<?php if ( '' !== $npc['secret'] ) : ?><p class="pspp-multiline"><strong><?php esc_html_e( 'Geheimnisse – nur Spielleitung', 'ps-pen-paper' ); ?></strong><br><?php echo esc_html( $npc['secret'] ); ?></p><?php endif; ?>
		<?php if ( $state['source'] ) : ?><p><?php echo esc_html( __( 'Übernommener Vorlagenstand:', 'ps-pen-paper' ) . ' #' . $state['source']['record_id'] . ' · ' . __( 'Revision', 'ps-pen-paper' ) . ' ' . $state['source']['revision'] ); ?></p><?php endif; ?>
	</article>
	<?php
}

function ps_pen_paper_render_npc_confirmation( $scope, $page_id, $id, $state, $operation, $label, $notice ) {
	ps_pen_paper_form_start( $operation, 'pspp_campaign_' . $scope->ID, $scope->ID, $page_id );
	?>
		<input type="hidden" name="npc_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="npc_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
		<label class="pspp-check"><input type="checkbox" name="npc_confirmation" value="confirmed" required><?php echo esc_html( $notice ); ?></label>
		<button type="submit"><?php echo esc_html( $label ); ?></button>
	</form>
	<?php
}

function ps_pen_paper_render_npcs( $scope, $page_id ) {
	if ( ! ps_pen_paper_can_read_npcs( $scope ) || ! ps_pen_paper_can_manage_character_scope( $scope ) ) { return; }
	$records = ps_pen_paper_npc_records( $scope );
	$id = ps_pen_paper_frontend_id( 'pspp_npc' );
	$open = ps_pen_paper_frontend_id( 'pspp_npcs' );
	$state = is_wp_error( $id ) ? $id : ( $id ? ps_pen_paper_get_npc( $scope, $id ) : ps_pen_paper_npc_default( $scope ) );
	if ( is_wp_error( $records ) || is_wp_error( $state ) || is_wp_error( $open ) ) { echo '<p role="alert">' . esc_html__( 'Die Figurenverwaltung konnte nicht geladen werden. Bitte prüfe die Auswahl.', 'ps-pen-paper' ) . '</p>'; return; }
	$universe_scope = 'pspp_universe' === $scope->post_type;
	$npc = $state['npc'] ?: array( 'kind' => 'npc', 'name' => '', 'description' => '', 'secret' => '', 'properties' => array(), 'values' => array(), 'scene_id' => 0, 'asset_id' => 0 );
	?>
	<section id="pspp-npcs" class="pspp-npcs">
	<h2><?php esc_html_e( 'Gegner & NPCs', 'ps-pen-paper' ); ?></h2>
	<p><?php esc_html_e( 'Private Figuren mit freien Eigenschaften und Werten. Personenkarten bleiben erzählerische Notizen. Kein festes Kampfsystem, keine automatische Beute und keine Veröffentlichung im Wiki.', 'ps-pen-paper' ); ?></p>
	<details class="pspp-fold"<?php if ( $id || $open ) { echo ' open'; } ?>>
		<summary><?php esc_html_e( 'Figuren und Vorlagen verwalten', 'ps-pen-paper' ); ?></summary>
		<p><a class="button" href="<?php echo esc_url( ps_pen_paper_npc_url( $scope, $page_id ) ); ?>"><?php echo esc_html( $universe_scope ? __( 'Neue Universumsvorlage', 'ps-pen-paper' ) : __( 'Neue Kampagnenfigur', 'ps-pen-paper' ) ); ?></a></p>
		<ul><?php foreach ( $records as $record_id => $record ) : ?><li><a href="<?php echo esc_url( ps_pen_paper_npc_url( $scope, $page_id, $record_id ) ); ?>"><?php echo esc_html( $record['npc']['name'] ); ?></a> · <?php echo esc_html( ps_pen_paper_npc_statuses()[ $record['status'] ] ); ?></li><?php endforeach; ?></ul>
		<?php if ( $id ) { ps_pen_paper_render_npc_card( $scope, $state ); } ?>
		<h3><?php echo esc_html( $id ? __( 'Figur bearbeiten', 'ps-pen-paper' ) : __( 'Figur anlegen', 'ps-pen-paper' ) ); ?></h3>
		<?php ps_pen_paper_form_start( 'save_npc', 'pspp_campaign_' . $scope->ID, $scope->ID, $page_id, true ); ?>
			<input type="hidden" name="npc_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="npc_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
			<label><?php esc_html_e( 'Figurenart', 'ps-pen-paper' ); ?><select name="npc[kind]"><?php foreach ( ps_pen_paper_npc_kinds() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $npc['kind'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label><?php esc_html_e( 'Name der Figur', 'ps-pen-paper' ); ?><input type="text" name="npc[name]" maxlength="200" value="<?php echo esc_attr( $npc['name'] ); ?>" required></label>
			<label><?php esc_html_e( 'Beschreibung der Figur', 'ps-pen-paper' ); ?><textarea name="npc[description]" rows="4" maxlength="20000"><?php echo esc_textarea( $npc['description'] ); ?></textarea></label>
			<label><?php esc_html_e( 'Geheimnisse der Figur – nur Spielleitung', 'ps-pen-paper' ); ?><textarea name="npc[secret]" rows="3" maxlength="20000"><?php echo esc_textarea( $npc['secret'] ); ?></textarea></label>
			<?php foreach ( array( 'properties' => __( 'Eigenschaften: je Zeile Bezeichnung | Text', 'ps-pen-paper' ), 'values' => __( 'Werte: je Zeile Bezeichnung | ganze Zahl', 'ps-pen-paper' ) ) as $key => $label ) :
				$lines = array(); foreach ( $npc[ $key ] as $field ) { $lines[] = $field['label'] . ' | ' . $field['value']; } ?>
				<label><?php echo esc_html( $label ); ?><textarea name="npc[<?php echo esc_attr( $key ); ?>]" rows="4" maxlength="20000"><?php echo esc_textarea( implode( "\n", $lines ) ); ?></textarea></label>
			<?php endforeach; ?>
			<p><?php esc_html_e( 'Höchstens 30 Eigenschaften und 30 Werte; Zahlen zwischen -1000000 und 1000000. Die Werte sind unabhängig von Spielerbögen und lösen keine automatischen Kampfregeln aus.', 'ps-pen-paper' ); ?></p>
			<?php if ( ! $universe_scope ) : ?>
				<label><?php esc_html_e( 'Szenenverknüpfung', 'ps-pen-paper' ); ?><select name="npc[scene_id]"><option value="0"><?php esc_html_e( 'Keine', 'ps-pen-paper' ); ?></option>
				<?php foreach ( get_posts( array( 'post_type' => 'pspp_scene', 'post_status' => 'private', 'post_parent' => $scope->ID, 'posts_per_page' => -1 ) ) as $scene_post ) :
					$scene = ps_pen_paper_get_scene( $scope, $scene_post->ID );
					if ( is_wp_error( $scene ) ) { echo '<option disabled>' . esc_html( $scene->get_error_message() ) . '</option>'; continue; }
					?><option value="<?php echo esc_attr( $scene_post->ID ); ?>" <?php selected( $npc['scene_id'], $scene_post->ID ); ?>><?php echo esc_html( $scene['draft']['title'] ); ?></option>
				<?php endforeach; ?></select></label>
			<?php else : ?><input type="hidden" name="npc[scene_id]" value="0"><?php endif; ?>
			<label><?php esc_html_e( 'Geschütztes Figurenbild (optional)', 'ps-pen-paper' ); ?><input type="file" name="npc_image" accept="image/png,image/jpeg,image/webp"></label>
			<p><?php esc_html_e( 'PNG, JPEG oder WebP bis 2 MiB und 3000 × 3000 Pixel. Keine öffentliche Mediathek-URL.', 'ps-pen-paper' ); ?></p>
			<?php if ( $npc['asset_id'] ) : ?><label class="pspp-check"><input type="checkbox" name="npc[remove_image]" value="1"><?php esc_html_e( 'Figurenbild entfernen (nicht gleichzeitig ein neues hochladen)', 'ps-pen-paper' ); ?></label><?php endif; ?>
			<input type="hidden" name="npc_complete" value="complete">
			<button type="submit"><?php esc_html_e( 'Figur speichern', 'ps-pen-paper' ); ?></button>
		</form>
		<?php if ( $id && 'proposed' === $state['status'] ) {
			ps_pen_paper_render_npc_confirmation( $scope, $page_id, $id, $state, 'approve_npc', __( 'Aufnahme ins Universum bestätigen', 'ps-pen-paper' ), __( 'Diesen gespeicherten Stand einschließlich Geheimnissen als Vorlage für die Spielleitungen der zugeordneten Kampagnen bereitstellen.', 'ps-pen-paper' ) );
		} ?>
		<?php if ( ! $universe_scope ) :
			$link = ps_pen_paper_get_universe_link( $scope->ID );
			if ( is_wp_error( $link ) ) : ?><p role="alert"><?php echo esc_html( $link->get_error_message() ); ?></p>
			<?php elseif ( $link['universe_id'] ) :
				$universe = ps_pen_paper_get_universe( $link['universe_id'] );
				$catalogue = ps_pen_paper_npc_records( $universe, $scope );
				if ( $id ) { ps_pen_paper_render_npc_confirmation( $scope, $page_id, $id, $state, 'offer_npc',
					ps_pen_paper_can_manage_universe( $universe ) ? __( 'Ins Universum aufnehmen', 'ps-pen-paper' ) : __( 'Aufnahme ins Universum vorschlagen', 'ps-pen-paper' ),
					__( 'Beschreibung, Bild, Werte und Geheimnisse ausdrücklich mit der Universumsleitung teilen. Erst nach ihrer Zustimmung steht die Vorlage anderen Kampagnenleitungen zur Verfügung. Spätere Änderungen werden nicht automatisch synchronisiert.', 'ps-pen-paper' ) ); } ?>
				<h3><?php esc_html_e( 'Vorlagen aus dem Universum', 'ps-pen-paper' ); ?></h3>
				<?php if ( is_wp_error( $catalogue ) ) : ?><p role="alert"><?php echo esc_html( $catalogue->get_error_message() ); ?></p>
				<?php else : foreach ( $catalogue as $template_id => $template ) : if ( 'template' !== $template['status'] ) { continue; } ?>
					<div data-npc-template="<?php echo esc_attr( $template_id ); ?>">
					<?php ps_pen_paper_render_npc_card( $universe, $template, $scope );
					ps_pen_paper_render_npc_confirmation( $scope, $page_id, $template_id, $template, 'copy_npc', __( 'Als Kampagnenfigur übernehmen', 'ps-pen-paper' ), __( 'Diesen Vorlagenstand als unabhängige Figur übernehmen. Lokale Änderungen wirken nicht auf die Vorlage; die Szenenzuordnung bleibt zunächst leer.', 'ps-pen-paper' ) ); ?>
					</div>
				<?php endforeach; endif; ?>
			<?php else : ?><p><?php esc_html_e( 'Diese Kampagne ist keinem Universum zugeordnet. Ihre Figuren bleiben lokal.', 'ps-pen-paper' ); ?></p><?php endif;
		endif; ?>
	</details>
	</section>
	<?php
}

function ps_pen_paper_render_scene_npcs( $campaign, $scene_id, $page_id ) {
	if ( ! ps_pen_paper_can_read_npcs( $campaign ) ) { return; }
	$records = ps_pen_paper_npc_records( $campaign );
	if ( is_wp_error( $records ) ) { echo '<p role="alert">' . esc_html( $records->get_error_message() ) . '</p>'; return; }
	foreach ( $records as $id => $state ) {
		if ( $state['npc']['scene_id'] !== $scene_id ) { continue; }
		?><details class="pspp-fold"><summary><?php echo esc_html( ps_pen_paper_npc_kinds()[ $state['npc']['kind'] ] . ': ' . $state['npc']['name'] ); ?></summary>
			<?php ps_pen_paper_render_npc_card( $campaign, $state ); ?>
			<a href="<?php echo esc_url( ps_pen_paper_npc_url( $campaign, $page_id, $id ) ); ?>"><?php esc_html_e( 'Figur öffnen', 'ps-pen-paper' ); ?></a>
		</details><?php
	}
}
