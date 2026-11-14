<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_handle_folio_action( $operation ) {
	$campaign_id = ps_pen_paper_posted_id( 'campaign_id' );
	$page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
	check_admin_referer( 'pspp_campaign_' . $campaign_id );
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	if ( ! $campaign || ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) ||
		( isset( $_POST['pspp_frontend_page'] ) && ! ps_pen_paper_frontend_page( $page_id ) ) ) {
		ps_pen_paper_fail( __( 'Diese Spielleitermappe ist für dich nicht verfügbar.', 'ps-pen-paper' ) );
	}
	$id = ps_pen_paper_posted_id( 'folio_id' );
	$revision = ps_pen_paper_posted_text( 'folio_revision' );
	if ( 'save_folio' === $operation ) {
		if ( 'complete' !== ps_pen_paper_posted_text( 'folio_complete' ) ) { ps_pen_paper_fail( __( 'Das Mappenformular wurde nicht vollständig übertragen.', 'ps-pen-paper' ) ); }
		$path = null;
		if ( isset( $_FILES['folio_image'] ) ) {
			$file = $_FILES['folio_image'];
			if ( ! is_array( $file ) || ! isset( $file['error'] ) ||
				( UPLOAD_ERR_NO_FILE !== $file['error'] && ( UPLOAD_ERR_OK !== $file['error'] || ! isset( $file['tmp_name'] ) || ! is_string( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) ) ) {
				ps_pen_paper_fail( __( 'Das Handoutbild wurde nicht vollständig hochgeladen.', 'ps-pen-paper' ) );
			}
			if ( UPLOAD_ERR_OK === $file['error'] ) { $path = $file['tmp_name']; }
		}
		$result = ps_pen_paper_save_folio( $campaign, $id, isset( $_POST['folio'] ) ? wp_unslash( $_POST['folio'] ) : null, $revision, $path );
		if ( ! is_wp_error( $result ) ) { $id = $result; }
	} else {
		$result = ps_pen_paper_publish_folio( $campaign, $id, $revision, ps_pen_paper_posted_text( 'folio_confirmation' ), 'withdraw_folio' === $operation );
	}
	if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
	$url = $page_id ? ps_pen_paper_game_url( $page_id, $campaign_id ) : ps_pen_paper_admin_url( $campaign_id );
	$target = 'story' === ps_pen_paper_posted_text( 'folio_return' ) ? '#pspp-story' : '#pspp-folio';
	wp_safe_redirect( add_query_arg( array( 'pspp_folio' => $id, 'pspp_notice' => 'folio_saved' ), $url ) . $target );
	exit;
}

function ps_pen_paper_render_handout( $campaign, $snapshot ) {
	?>
	<article class="pspp-handout">
		<h3><?php echo esc_html( $snapshot['title'] ); ?></h3>
		<div class="pspp-multiline"><?php echo esc_html( $snapshot['text'] ); ?></div>
		<?php if ( $snapshot['asset_id'] ) :
			$image = ps_pen_paper_private_image( $campaign, $snapshot['asset_id'] );
			if ( is_wp_error( $image ) ) : ?><p role="alert"><?php echo esc_html( $image->get_error_message() ); ?></p>
			<?php else : ?><img src="<?php echo esc_attr( $image ); ?>" alt="<?php echo esc_attr( $snapshot['title'] ); ?>" loading="lazy"><?php endif;
		endif; ?>
	</article>
	<?php
}

function ps_pen_paper_render_handouts( $campaign, $page_id ) {
	$handouts = ps_pen_paper_visible_handouts( $campaign );
	?>
	<h2><?php esc_html_e( 'Handouts der Runde', 'ps-pen-paper' ); ?></h2>
	<p><?php esc_html_e( 'Briefe, Bilder und Hinweise, die eure Spielleitung auf den Tisch gelegt hat. Sie bleiben bis zur Rücknahme verfügbar, auch nach einem Szenenwechsel.', 'ps-pen-paper' ); ?></p>
	<a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-handouts' ); ?>"><?php esc_html_e( 'Handouts neu laden', 'ps-pen-paper' ); ?></a>
	<?php
	if ( is_wp_error( $handouts ) ) { echo '<p role="alert">' . esc_html( $handouts->get_error_message() ) . '</p>'; return; }
	if ( ! $handouts ) { echo '<p>' . esc_html__( 'Noch keine Handouts freigegeben.', 'ps-pen-paper' ) . '</p>'; }
	foreach ( $handouts as $snapshot ) { ps_pen_paper_render_handout( $campaign, $snapshot ); }
}

function ps_pen_paper_render_folio( $campaign, $page_id ) {
	$records = ps_pen_paper_folio_records( $campaign );
	if ( is_wp_error( $records ) ) { echo '<p role="alert">' . esc_html( $records->get_error_message() ) . '</p>'; return; }
	$id = ps_pen_paper_frontend_id( 'pspp_folio' );
	$state = is_wp_error( $id ) ? $id : ( $id ? ps_pen_paper_get_folio( $campaign, $id ) : ps_pen_paper_folio_default() );
	if ( is_wp_error( $state ) ) { echo '<p role="alert">' . esc_html( $state->get_error_message() ) . '</p>'; return; }
	$draft = $state['draft'];
	$types = ps_pen_paper_folio_types();
	$url = $page_id ? ps_pen_paper_game_url( $page_id, $campaign->ID ) : ps_pen_paper_admin_url( $campaign->ID );
	$scenes = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_scene', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
		$scene = ps_pen_paper_get_scene( $campaign, $post->ID );
		if ( is_wp_error( $scene ) ) { echo '<p role="alert">' . esc_html( $scene->get_error_message() ) . '</p>'; return; }
		$scenes[ $post->ID ] = $scene;
	}
	?>
	<h2><?php esc_html_e( 'Spielleitermappe', 'ps-pen-paper' ); ?></h2>
	<p><?php esc_html_e( 'Kapitel ordnen dein Abenteuer. Personen, Orte und Notizen bleiben privat. Nur Handouts können ausdrücklich für die gesamte Runde freigegeben werden; Verknüpfungen geben niemals Inhalte automatisch frei.', 'ps-pen-paper' ); ?></p>
	<p><a href="<?php echo esc_url( ps_pen_paper_npc_url( $campaign, $page_id ) ); ?>"><?php esc_html_e( 'Gegner und NPCs verwalten', 'ps-pen-paper' ); ?></a></p>
	<a class="button" href="<?php echo esc_url( $url . '#pspp-folio' ); ?>"><?php esc_html_e( 'Neuer Mappeneintrag', 'ps-pen-paper' ); ?></a>
	<div class="pspp-folio-index">
	<?php foreach ( $types as $type => $label ) : ?>
		<section><h3><?php echo esc_html( $label ); ?></h3><ul>
		<?php foreach ( $records as $record_id => $record ) :
			if ( $type !== $record['draft']['type'] ) { continue; }
			?><li><a href="<?php echo esc_url( add_query_arg( 'pspp_folio', $record_id, $url ) . '#pspp-folio' ); ?>"><?php echo esc_html( $record['draft']['title'] ); ?></a>
				<?php echo null !== $record['published'] ? ' · ' . esc_html__( 'freigegeben', 'ps-pen-paper' ) : ' · ' . esc_html__( 'privat', 'ps-pen-paper' ); ?>
				<?php if ( $record['draft']['chapter_id'] && isset( $records[ $record['draft']['chapter_id'] ] ) ) : ?><small><?php echo esc_html( $records[ $record['draft']['chapter_id'] ]['draft']['title'] ); ?></small><?php endif; ?>
				<?php if ( $record['draft']['scene_id'] && isset( $scenes[ $record['draft']['scene_id'] ] ) ) : ?><small><a href="<?php echo esc_url( add_query_arg( 'pspp_scene', $record['draft']['scene_id'], $url ) . '#pspp-story' ); ?>"><?php echo esc_html( $scenes[ $record['draft']['scene_id'] ]['draft']['title'] ); ?></a></small><?php endif; ?>
				<?php if ( 'chapter' === $type ) : ?><ol><?php foreach ( $record['draft']['scene_ids'] as $scene_id ) :
					if ( ! isset( $scenes[ $scene_id ] ) ) { echo '<li role="alert">' . esc_html__( 'Eine zugeordnete Szene fehlt.', 'ps-pen-paper' ) . '</li>'; continue; }
					?><li><a href="<?php echo esc_url( add_query_arg( 'pspp_scene', $scene_id, $url ) . '#pspp-story' ); ?>"><?php echo esc_html( $scenes[ $scene_id ]['draft']['title'] ); ?></a> · <?php echo esc_html( $scenes[ $scene_id ]['published'] ? __( 'freigegeben', 'ps-pen-paper' ) : __( 'Entwurf', 'ps-pen-paper' ) ); ?></li>
				<?php endforeach; ?></ol><?php endif; ?>
			</li>
		<?php endforeach; ?></ul></section>
	<?php endforeach; ?>
	</div>
	<h3><?php echo esc_html( $id ? $draft['title'] : __( 'Neuer Mappeneintrag', 'ps-pen-paper' ) ); ?></h3>
	<?php ps_pen_paper_form_start( 'save_folio', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id, true ); ?>
		<input type="hidden" name="folio_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="folio_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
		<label><?php esc_html_e( 'Art des Eintrags', 'ps-pen-paper' ); ?><select name="folio[type]"<?php if ( $id ) { echo ' disabled'; } ?>>
			<?php foreach ( $types as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $draft['type'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
		</select></label>
		<?php if ( $id ) : ?><input type="hidden" name="folio[type]" value="<?php echo esc_attr( $draft['type'] ); ?>"><?php endif; ?>
		<label><?php esc_html_e( 'Titel des Eintrags', 'ps-pen-paper' ); ?><input type="text" name="folio[title]" maxlength="200" value="<?php echo esc_attr( $draft['title'] ); ?>" required></label>
		<label><?php esc_html_e( 'Inhalt / Handouttext', 'ps-pen-paper' ); ?><textarea name="folio[text]" rows="7"><?php echo esc_textarea( $draft['text'] ); ?></textarea></label>
		<label><?php esc_html_e( 'Geheime Ergänzungen – niemals Teil des Handouts', 'ps-pen-paper' ); ?><textarea name="folio[secret]" rows="4"><?php echo esc_textarea( $draft['secret'] ); ?></textarea></label>
		<label><?php esc_html_e( 'Sortiernummer (kleinere zuerst)', 'ps-pen-paper' ); ?><input type="number" name="folio[order]" min="0" max="9999" value="<?php echo esc_attr( $draft['order'] ); ?>" required></label>
		<label><?php esc_html_e( 'Kapitelverknüpfung (nicht für Kapitel selbst)', 'ps-pen-paper' ); ?><select name="folio[chapter_id]"><option value="0"><?php esc_html_e( 'Keine', 'ps-pen-paper' ); ?></option>
		<?php foreach ( $records as $record_id => $record ) : if ( 'chapter' !== $record['draft']['type'] || $record_id === $id ) { continue; } ?><option value="<?php echo esc_attr( $record_id ); ?>" <?php selected( $draft['chapter_id'], $record_id ); ?>><?php echo esc_html( $record['draft']['title'] ); ?></option><?php endforeach; ?>
		</select></label>
		<label><?php esc_html_e( 'Szenenverknüpfung', 'ps-pen-paper' ); ?><select name="folio[scene_id]"><option value="0"><?php esc_html_e( 'Keine', 'ps-pen-paper' ); ?></option>
		<?php foreach ( $scenes as $scene_id => $scene ) : ?><option value="<?php echo esc_attr( $scene_id ); ?>" <?php selected( $draft['scene_id'], $scene_id ); ?>><?php echo esc_html( $scene['draft']['title'] ); ?></option><?php endforeach; ?>
		</select></label>
		<?php if ( $id && 'chapter' === $draft['type'] ) : ?>
			<fieldset class="pspp-field"><legend><?php esc_html_e( 'Szenen im Kapitel', 'ps-pen-paper' ); ?></legend>
				<p><?php esc_html_e( 'Eine Nummer nimmt die Szene auf und bestimmt ihre Reihenfolge. Leer bedeutet: nicht in diesem Kapitel. Freigabe und aktive Szene bleiben unverändert.', 'ps-pen-paper' ); ?></p>
				<?php foreach ( $scenes as $scene_id => $scene ) : $position = array_search( $scene_id, $draft['scene_ids'], true ); ?>
					<label><?php echo esc_html( $scene['draft']['title'] ); ?><input type="number" min="0" max="9999" name="folio[scenes][<?php echo esc_attr( $scene_id ); ?>]" value="<?php echo false === $position ? '' : esc_attr( $position + 1 ); ?>"></label>
				<?php endforeach; ?>
			</fieldset>
		<?php endif; ?>
		<?php if ( ! $id || 'handout' === $draft['type'] ) : ?>
			<label><?php esc_html_e( 'Optionales Handoutbild (nur für Handouts)', 'ps-pen-paper' ); ?><input type="file" name="folio_image" accept="image/png,image/jpeg,image/webp"></label>
			<p><?php esc_html_e( 'PNG, JPEG oder WebP bis 2 MiB und 3000 × 3000 Pixel. Keine öffentliche Mediathek-URL. PDFs und andere Dateien werden hier nicht angenommen.', 'ps-pen-paper' ); ?></p>
			<?php if ( $draft['asset_id'] ) : ?><label class="pspp-check"><input type="checkbox" name="folio[remove_image]" value="1"><?php esc_html_e( 'Bild aus dem Entwurf entfernen (nicht gleichzeitig ein neues hochladen)', 'ps-pen-paper' ); ?></label><?php endif; ?>
		<?php endif; ?>
		<input type="hidden" name="folio_complete" value="complete">
		<button type="submit"><?php esc_html_e( 'Mappeneintrag speichern', 'ps-pen-paper' ); ?></button>
	</form>
	<?php if ( $id && 'handout' === $draft['type'] ) : ?>
		<details class="pspp-fold"><summary><?php esc_html_e( 'Private Vorschau des gespeicherten Handoutentwurfs', 'ps-pen-paper' ); ?></summary>
			<?php ps_pen_paper_render_handout( $campaign, $draft ); ?>
		</details>
		<p><?php esc_html_e( 'Die Freigabe übernimmt nur Titel, Text und Bild. Entwurfsänderungen bleiben privat, bis du erneut freigibst. Bereits gelesene oder gespeicherte Inhalte können nicht zurückgeholt werden.', 'ps-pen-paper' ); ?></p>
		<?php foreach ( array( 'publish_folio' => __( 'Handout für die Runde freigeben', 'ps-pen-paper' ), 'withdraw_folio' => __( 'Handout zurücknehmen', 'ps-pen-paper' ) ) as $operation => $label ) :
			if ( 'withdraw_folio' === $operation && null === $state['published'] ) { continue; }
			ps_pen_paper_form_start( $operation, 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
			<input type="hidden" name="folio_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="folio_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
			<label class="pspp-check"><input type="checkbox" name="folio_confirmation" value="confirmed" required><?php echo esc_html( $label ); ?></label>
			<button type="submit"><?php echo esc_html( $label ); ?></button>
			</form>
		<?php endforeach; ?>
	<?php endif;
	ps_pen_paper_render_npcs( $campaign, $page_id );
}
