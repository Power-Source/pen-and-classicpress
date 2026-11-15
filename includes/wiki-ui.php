<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_wiki_url( $universe, $page_id, $entry_id = 0 ) {
	$url = add_query_arg( 'pspp_wiki', 1, ps_pen_paper_universe_url( $universe->ID, $page_id ) );
	if ( $entry_id ) { $url = add_query_arg( 'pspp_wiki_entry', $entry_id, $url ); }
	return $url . '#pspp-wiki';
}

function ps_pen_paper_handle_wiki_link_action() {
	$campaign_id = ps_pen_paper_posted_id( 'campaign_id' ); $page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
	check_admin_referer( 'pspp_campaign_' . $campaign_id );
	if ( isset( $_POST['pspp_frontend_page'] ) && ! ps_pen_paper_frontend_page( $page_id ) ) { ps_pen_paper_fail( ps_pen_paper_wiki_error()->get_error_message() ); }
	$entry = ps_pen_paper_posted_text( 'wiki_link_entry' );
	if ( ! preg_match( '/^(?:0|[1-9]\d{0,10})$/D', $entry ) ) { ps_pen_paper_fail( ps_pen_paper_wiki_error()->get_error_message() ); }
	$result = ps_pen_paper_save_wiki_link( ps_pen_paper_get_campaign( $campaign_id ), ps_pen_paper_posted_text( 'wiki_link_target' ),
		(int) $entry, ps_pen_paper_posted_text( 'wiki_link_revision' ), ps_pen_paper_posted_text( 'wiki_link_rules_revision' ) );
	if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
	$url = $page_id ? ps_pen_paper_game_url( $page_id, $campaign_id ) : ps_pen_paper_admin_url( $campaign_id );
	wp_safe_redirect( $url . '#pspp-wiki-links' ); exit;
}

function ps_pen_paper_render_wiki_target_link( $campaign, $target ) {
	$link = ps_pen_paper_wiki_link_for_target( $campaign, $target );
	if ( is_wp_error( $link ) ) { echo '<span role="alert">' . esc_html__( 'Lexikonverknüpfung nicht verfügbar.', 'ps-pen-paper' ) . '</span>'; }
	elseif ( $link ) { echo '<a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['title'] ) . '</a>'; }
}

function ps_pen_paper_render_campaign_wiki_links( $campaign, $page_id ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) ) { return; }
	$state = ps_pen_paper_wiki_links( $campaign ); $rules = ps_pen_paper_get_rules( $campaign->ID );
	$link = ps_pen_paper_get_universe_link( $campaign->ID );
	if ( is_wp_error( $state ) || is_wp_error( $link ) ) { echo '<p role="alert">' . esc_html__( 'Lexikonverknüpfungen sind nicht verfügbar.', 'ps-pen-paper' ) . '</p>'; return; }
	?><section id="pspp-wiki-links"><h3><?php esc_html_e( 'Lexikonverknüpfungen', 'ps-pen-paper' ); ?></h3>
	<p><?php esc_html_e( 'Verknüpfe Szenen oder Charakterfelder mit öffentlichen Artikeln des zugeordneten Universums. Die Verknüpfung veröffentlicht keine Kampagnendaten und vergibt keine Rechte.', 'ps-pen-paper' ); ?></p>
	<?php if ( ! $link['universe_id'] || ! ps_pen_paper_wiki_available() ) : ?>
		<p><?php esc_html_e( 'Ein zugeordnetes Universum und PS Wiki sind erforderlich.', 'ps-pen-paper' ); ?></p>
	<?php else :
		$targets = array();
		foreach ( get_posts( array( 'post_type' => 'pspp_scene', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => -1 ) ) as $post ) {
			$scene = ps_pen_paper_get_scene( $campaign, $post->ID );
			if ( is_wp_error( $scene ) ) { echo '<p role="alert">' . esc_html( $scene->get_error_message() ) . '</p></section>'; return; }
			$targets[ 'scene:' . $post->ID ] = __( 'Szene:', 'ps-pen-paper' ) . ' ' . $scene['draft']['title'];
		}
		foreach ( $rules['fields'] as $field ) { $targets[ 'field:' . $field['id'] ] = __( 'Charakterfeld:', 'ps-pen-paper' ) . ' ' . $field['label']; }
		$entries = array();
		foreach ( get_posts( array( 'post_type' => 'pspp_wiki_entry', 'post_status' => 'private', 'post_parent' => $link['universe_id'], 'posts_per_page' => -1 ) ) as $post ) {
			$entry = ps_pen_paper_public_wiki_link_entry( $campaign, (int) $post->ID );
			if ( ! is_wp_error( $entry ) ) { $entries[ $post->ID ] = $entry['title']; }
		}
		foreach ( $state['links'] as $target => $entry_id ) : ?>
			<p><?php echo esc_html( isset( $targets[ $target ] ) ? $targets[ $target ] : $target ); ?> · <?php echo esc_html( isset( $entries[ $entry_id ] ) ? $entries[ $entry_id ] : __( 'Artikel nicht verfügbar', 'ps-pen-paper' ) ); ?></p>
			<?php ps_pen_paper_form_start( 'save_wiki_link', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
				<input type="hidden" name="wiki_link_revision" value="<?php echo esc_attr( $state['revision'] ); ?>"><input type="hidden" name="wiki_link_rules_revision" value="<?php echo esc_attr( $rules['revision'] ); ?>">
				<input type="hidden" name="wiki_link_target" value="<?php echo esc_attr( $target ); ?>"><input type="hidden" name="wiki_link_entry" value="0">
				<button type="submit"><?php esc_html_e( 'Verknüpfung entfernen', 'ps-pen-paper' ); ?></button></form>
		<?php endforeach;
		if ( $targets && $entries ) :
			ps_pen_paper_form_start( 'save_wiki_link', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
			<input type="hidden" name="wiki_link_revision" value="<?php echo esc_attr( $state['revision'] ); ?>"><input type="hidden" name="wiki_link_rules_revision" value="<?php echo esc_attr( $rules['revision'] ); ?>">
			<label><?php esc_html_e( 'Szene oder Feld', 'ps-pen-paper' ); ?><select name="wiki_link_target"><?php foreach ( $targets as $target => $label ) : ?><option value="<?php echo esc_attr( $target ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label><?php esc_html_e( 'Öffentlicher Artikel', 'ps-pen-paper' ); ?><select name="wiki_link_entry"><?php foreach ( $entries as $entry_id => $title ) : ?><option value="<?php echo esc_attr( $entry_id ); ?>"><?php echo esc_html( $title ); ?></option><?php endforeach; ?></select></label>
			<button type="submit"><?php esc_html_e( 'Verknüpfung speichern', 'ps-pen-paper' ); ?></button></form>
		<?php endif;
	endif; ?></section><?php
}

function ps_pen_paper_handle_wiki_action( $operation ) {
	$id = ps_pen_paper_posted_id( 'campaign_id' ); $page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
	check_admin_referer( 'pspp_campaign_' . $id );
	$universe = ps_pen_paper_get_universe( $id );
	if ( ! ps_pen_paper_can_manage_wiki( $universe ) || ( isset( $_POST['pspp_frontend_page'] ) && ! ps_pen_paper_frontend_page( $page_id ) ) ) {
		ps_pen_paper_fail( __( 'Diese Lexikonverwaltung ist für dich nicht verfügbar.', 'ps-pen-paper' ) );
	}
	$entry_id = ps_pen_paper_posted_id( 'wiki_entry_id' );
	$input = isset( $_POST['wiki'] ) ? wp_unslash( $_POST['wiki'] ) : null;
	if ( 'prepare_wiki' === $operation && ! $entry_id && is_array( $input ) ) {
		if ( ! preg_match( '/^(0|[1-9]\d{0,10}):(0|[1-9]\d{0,10})$/D', ps_pen_paper_posted_text( 'wiki_source' ), $parts ) ) { ps_pen_paper_fail( ps_pen_paper_wiki_error()->get_error_message() ); }
		$input['source_id'] = $parts[1]; $input['source_revision'] = $parts[2];
	}
	if ( 'complete' !== ps_pen_paper_posted_text( 'wiki_complete' ) ) { ps_pen_paper_fail( __( 'Das Lexikonformular wurde nicht vollständig übertragen.', 'ps-pen-paper' ) ); }
	$result = 'configure_wiki' === $operation
		? ps_pen_paper_configure_wiki( $universe, $input, ps_pen_paper_posted_text( 'wiki_config_revision' ), ps_pen_paper_posted_text( 'wiki_confirmation' ) )
		: ps_pen_paper_save_wiki_entry( $universe, $entry_id, $input, ps_pen_paper_posted_text( 'wiki_revision' ), ps_pen_paper_posted_text( 'wiki_config_revision' ), ps_pen_paper_posted_text( 'wiki_confirmation' ), 'publish_wiki' === $operation );
	if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
	if ( 'configure_wiki' !== $operation ) { $entry_id = $result; }
	wp_safe_redirect( ps_pen_paper_wiki_url( $universe, $page_id, $entry_id ) );
	exit;
}

function ps_pen_paper_render_wiki_snapshot( $state ) {
	if ( ! $state['revision'] ) { echo '<p>' . esc_html__( 'Noch kein Lexikonstand vorhanden.', 'ps-pen-paper' ) . '</p>'; return; }
	if ( isset( $state['enabled'] ) ) {
		echo '<h4>' . esc_html( $state['name'] ) . '</h4><p>' . esc_html( $state['enabled'] ? __( 'Anbindung aktiv', 'ps-pen-paper' ) : __( 'Anbindung pausiert', 'ps-pen-paper' ) ) . '</p><ul>';
		foreach ( $state['categories'] as $category ) { echo '<li>' . esc_html( $category['name'] ) . '</li>'; }
		echo '</ul>'; return;
	}
	echo '<h4>' . esc_html( $state['title'] ) . '</h4><p class="pspp-multiline">' . esc_html( $state['content'] ) . '</p>';
	echo '<p>' . esc_html( $state['published'] ? __( 'Dieser vorbereitete Stand wurde veröffentlicht.', 'ps-pen-paper' ) : __( 'Vorbereitet – noch nicht über diese Freigabe veröffentlicht. Eine bestehende öffentliche Fassung bleibt unverändert.', 'ps-pen-paper' ) ) . '</p>';
}

function ps_pen_paper_render_wiki_hidden( $config, $entry_id = 0, $state = null ) {
	?><input type="hidden" name="wiki_config_revision" value="<?php echo esc_attr( $config['revision'] ); ?>">
	<input type="hidden" name="wiki_complete" value="complete">
	<?php if ( null !== $state ) : ?>
		<input type="hidden" name="wiki_entry_id" value="<?php echo esc_attr( $entry_id ); ?>">
		<input type="hidden" name="wiki_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
	<?php endif;
}

function ps_pen_paper_render_wiki( $universe, $page_id ) {
	if ( ! ps_pen_paper_can_manage_wiki( $universe ) ) { return; }
	$config = ps_pen_paper_wiki_config( $universe );
	if ( is_wp_error( $config ) ) { echo '<p role="alert">' . esc_html( $config->get_error_message() ) . '</p>'; return; }
	?>
	<section id="pspp-wiki" class="pspp-npcs"><h2><?php esc_html_e( 'Öffentliches Weltlexikon · PS Wiki', 'ps-pen-paper' ); ?></h2>
	<p><?php esc_html_e( 'Optionales öffentliches Lexikon, kein privates Kampagnen-Wiki. Nur separat eingegebene öffentliche Namen und Texte werden übertragen. Geheimnisse, Bilder, Eigenschaften, Werte, Beutetabellen und interne Universumsbeschreibungen werden niemals automatisch übernommen.', 'ps-pen-paper' ); ?></p>
	<?php if ( ! ps_pen_paper_wiki_available() ) : ?>
		<p role="alert"><?php esc_html_e( 'Aktives PS Wiki mit Kategorien wird benötigt. Gespeicherte Zuordnungen bleiben erhalten; die Runde bleibt ohne Wiki spielbar.', 'ps-pen-paper' ); ?></p></section>
		<?php return;
	endif;
	$id = ps_pen_paper_frontend_id( 'pspp_wiki_entry' ); $open = ps_pen_paper_frontend_id( 'pspp_wiki' );
	$entries = ps_pen_paper_wiki_entries( $universe );
	$state = is_wp_error( $id ) ? $id : ( $id ? ps_pen_paper_get_wiki_entry( $universe, $id ) : ps_pen_paper_wiki_entry_default() );
	if ( is_wp_error( $entries ) || is_wp_error( $state ) || is_wp_error( $open ) ) { echo '<p role="alert">' . esc_html__( 'Die Lexikonauswahl ist ungültig. Bitte lade neu.', 'ps-pen-paper' ) . '</p></section>'; return; }
	?>
	<details class="pspp-fold"<?php if ( $open || $id ) { echo ' open'; } ?>><summary><?php esc_html_e( 'Lexikon einrichten und Artikel freigeben', 'ps-pen-paper' ); ?></summary>
	<?php if ( ps_pen_paper_can_configure_wiki( $universe ) ) : ?>
	<details class="pspp-fold"<?php if ( ! $config['revision'] ) { echo ' open'; } ?>><summary><?php esc_html_e( 'Öffentliche Kategorien und Anbindung', 'ps-pen-paper' ); ?></summary>
	<p><?php esc_html_e( 'Speichern legt sofort öffentlich sichtbare Kategorien an beziehungsweise benennt sie um – auch wenn noch kein Artikel veröffentlicht ist. Wähle ausdrücklich öffentliche Bezeichnungen. Pausieren löscht weder Kategorien noch veröffentlichte Artikel und nimmt keine Freigabe zurück.', 'ps-pen-paper' ); ?></p>
	<?php ps_pen_paper_form_start( 'configure_wiki', 'pspp_campaign_' . $universe->ID, $universe->ID, $page_id ); ps_pen_paper_render_wiki_hidden( $config ); ?>
		<label><?php esc_html_e( 'Öffentlicher Lexikonname (Hauptkategorie)', 'ps-pen-paper' ); ?><input type="text" name="wiki[name]" maxlength="200" value="<?php echo esc_attr( $config['name'] ); ?>" required></label>
		<?php foreach ( ps_pen_paper_wiki_categories() as $key => $label ) : ?>
			<label><?php echo esc_html( $label ); ?><input type="text" name="wiki[categories][<?php echo esc_attr( $key ); ?>]" maxlength="200" value="<?php echo esc_attr( isset( $config['categories'][ $key ] ) ? $config['categories'][ $key ]['name'] : $label ); ?>" required></label>
		<?php endforeach; ?>
		<label><?php esc_html_e( 'Anbindung', 'ps-pen-paper' ); ?><select name="wiki[enabled]"><option value="1" <?php selected( $config['enabled'], true ); ?>><?php esc_html_e( 'Aktivieren', 'ps-pen-paper' ); ?></option><option value="0" <?php selected( $config['revision'] && ! $config['enabled'], true ); ?>><?php esc_html_e( 'Pausieren – Veröffentlichungen bleiben bestehen', 'ps-pen-paper' ); ?></option></select></label>
		<label class="pspp-check"><input type="checkbox" name="wiki_confirmation" value="confirmed" required><?php esc_html_e( 'Diese Kategorienamen dürfen öffentlich sein. Der Universumsleitung ausdrücklich erlauben, Artikel für dieses Lexikon über den Pen-&-Paper-Adapter vorzubereiten und öffentlich zu veröffentlichen. Keine allgemeinen Wiki-Rechte vergeben.', 'ps-pen-paper' ); ?></label>
		<button type="submit"><?php esc_html_e( 'Lexikonanbindung speichern', 'ps-pen-paper' ); ?></button>
	</form></details>
	<?php else : ?><p><?php esc_html_e( 'Einrichtung, Kategorienänderung und Pausieren benötigen zusätzlich PS-Wiki-Veröffentlichungs- und Kategorienrechte, beispielsweise durch die Administration. Die Freigabe erlaubt der Universumsleitung nur die zugeordneten Artikel dieses Lexikons zu pflegen; native Wiki-Rechte bleiben unverändert.', 'ps-pen-paper' ); ?></p><?php endif; ?>
	<?php if ( $config['revision'] ) :
		$link = get_term_link( $config['root_id'], 'psource_wiki_category' );
		if ( is_wp_error( $link ) ) { echo '<p role="alert">' . esc_html( $link->get_error_message() ) . '</p>'; }
		else { echo '<p><a href="' . esc_url( $link ) . '">' . esc_html__( 'Öffentliche Lexikonkategorie öffnen', 'ps-pen-paper' ) . '</a></p>'; }
	endif; ?>
	<h3><?php esc_html_e( 'Zugeordnete Artikel', 'ps-pen-paper' ); ?></h3>
	<ul><?php foreach ( $entries as $entry_id => $entry ) : ?><li><a href="<?php echo esc_url( ps_pen_paper_wiki_url( $universe, $page_id, $entry_id ) ); ?>"><?php echo esc_html( $entry['title'] ); ?></a></li><?php endforeach; ?></ul>
	<?php if ( ! $config['enabled'] ) : ?><p><?php esc_html_e( 'Aktiviere die Anbindung, um Artikel vorzubereiten oder freizugeben.', 'ps-pen-paper' ); ?></p></details></section><?php return; endif;
	$records = ps_pen_paper_npc_records( $universe );
	if ( is_wp_error( $records ) ) { echo '<p role="alert">' . esc_html( $records->get_error_message() ) . '</p></details></section>'; return; }
	?>
	<p><a href="<?php echo esc_url( ps_pen_paper_wiki_url( $universe, $page_id ) ); ?>"><?php esc_html_e( 'Neuen Artikel vorbereiten', 'ps-pen-paper' ); ?></a></p>
	<?php if ( $id ) :
		ps_pen_paper_render_wiki_snapshot( $state );
		$fingerprint = ps_pen_paper_wiki_fingerprint( $state['wiki_id'], $universe->ID, $id );
		$conflict = is_wp_error( $fingerprint ) || $fingerprint !== $state['fingerprint'];
		if ( $conflict ) : ?><p role="alert"><?php echo esc_html( ps_pen_paper_wiki_error()->get_error_message() ); ?></p><?php endif;
		if ( 'publish' === get_post_status( $state['wiki_id'] ) ) : ?><p><a href="<?php echo esc_url( get_permalink( $state['wiki_id'] ) ); ?>"><?php esc_html_e( 'Öffentlichen Wiki-Artikel öffnen', 'ps-pen-paper' ); ?></a></p><?php endif;
		if ( ! $state['published'] && ! $conflict ) :
			ps_pen_paper_form_start( 'publish_wiki', 'pspp_campaign_' . $universe->ID, $universe->ID, $page_id );
			ps_pen_paper_render_wiki_hidden( $config, $id, $state );
			foreach ( array( 'source_id', 'source_revision', 'title', 'content', 'category' ) as $key ) : ?><input type="hidden" name="wiki[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $state[ $key ] ); ?>"><?php endforeach; ?>
			<label class="pspp-check"><input type="checkbox" name="wiki_confirmation" value="confirmed" required><?php esc_html_e( 'Genau diesen vorbereiteten Titel und Text öffentlich veröffentlichen. Er ersetzt gegebenenfalls die bisherige öffentliche Fassung.', 'ps-pen-paper' ); ?></label>
			<button type="submit"><?php esc_html_e( 'Artikel öffentlich veröffentlichen', 'ps-pen-paper' ); ?></button>
			</form>
		<?php endif;
		if ( $conflict ) { echo '</details></section>'; return; }
	endif;
	ps_pen_paper_form_start( 'prepare_wiki', 'pspp_campaign_' . $universe->ID, $universe->ID, $page_id );
	ps_pen_paper_render_wiki_hidden( $config, $id, $state ); ?>
		<h3><?php esc_html_e( 'Öffentlichen Artikeltext vorbereiten', 'ps-pen-paper' ); ?></h3>
		<?php if ( $id ) : ?><input type="hidden" name="wiki[source_id]" value="<?php echo esc_attr( $state['source_id'] ); ?>"><input type="hidden" name="wiki[source_revision]" value="<?php echo esc_attr( $state['source_id'] && isset( $records[ $state['source_id'] ] ) ? $records[ $state['source_id'] ]['revision'] : 0 ); ?>">
		<?php else : ?>
			<label><?php esc_html_e( 'Universumsvorlage (optional, nur bestätigte Vorlagen)', 'ps-pen-paper' ); ?><select name="wiki_source"><option value="0:0"><?php esc_html_e( 'Freier Welt-/Regelartikel', 'ps-pen-paper' ); ?></option>
			<?php foreach ( $records as $source_id => $record ) : if ( 'template' !== $record['status'] ) { continue; } ?><option value="<?php echo esc_attr( $source_id . ':' . $record['revision'] ); ?>"><?php echo esc_html( $record['npc']['name'] ); ?></option><?php endforeach; ?></select></label>
		<?php endif; ?>
		<label><?php esc_html_e( 'Kategorie', 'ps-pen-paper' ); ?><select name="wiki[category]"><?php foreach ( $config['categories'] as $key => $category ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $state['category'], $key ); ?>><?php echo esc_html( $category['name'] ); ?></option><?php endforeach; ?></select></label>
		<label><?php esc_html_e( 'Öffentlicher Artikeltitel', 'ps-pen-paper' ); ?><input type="text" name="wiki[title]" value="<?php echo esc_attr( $state['title'] ); ?>" maxlength="200" required></label>
		<label><?php esc_html_e( 'Öffentlicher Artikeltext (reiner Text)', 'ps-pen-paper' ); ?><textarea name="wiki[content]" rows="7" maxlength="20000" required><?php echo esc_textarea( $state['content'] ); ?></textarea></label>
		<label class="pspp-check"><input type="checkbox" name="wiki_confirmation" value="confirmed" required><?php esc_html_e( 'Nur diesen bewusst gewählten öffentlichen Text als Artikelentwurf vorbereiten. Wiki-Redakteure können Entwürfe im Wiki bearbeiten oder veröffentlichen. Keine vertraulichen Angaben eintragen.', 'ps-pen-paper' ); ?></label>
		<button type="submit"><?php esc_html_e( 'Artikelentwurf vorbereiten', 'ps-pen-paper' ); ?></button>
	</form>
	</details></section>
	<?php
}
