<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_equipment_upload( $field ) {
	if ( ! isset( $_FILES[ $field ] ) ) { return null; }
	$file = $_FILES[ $field ];
	if ( ! is_array( $file ) || ! isset( $file['error'] ) ) { ps_pen_paper_fail( __( 'Das Bild wurde nicht vollständig übertragen.', 'ps-pen-paper' ) ); }
	if ( UPLOAD_ERR_NO_FILE === $file['error'] ) { return null; }
	if ( UPLOAD_ERR_OK !== $file['error'] || ! isset( $file['tmp_name'] ) || ! is_string( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
		ps_pen_paper_fail( __( 'Das Bild wurde nicht vollständig übertragen.', 'ps-pen-paper' ) );
	}
	return $file['tmp_name'];
}

function ps_pen_paper_handle_equipment_action( $operation ) {
	$id = ps_pen_paper_posted_id( 'campaign_id' );
	check_admin_referer( 'pspp_campaign_' . $id );
	$scope = ps_pen_paper_get_character_scope( $id );
	$page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
	if ( ! $scope || ! ps_pen_paper_can_access_site() || ( isset( $_POST['pspp_frontend_page'] ) && ! ps_pen_paper_frontend_page( $page_id ) ) ) { ps_pen_paper_fail( __( 'Dieser Spielbereich ist nicht verfügbar.', 'ps-pen-paper' ) ); }
	if ( 'complete' !== ps_pen_paper_posted_text( 'equipment_complete' ) ) { ps_pen_paper_fail( __( 'Das Formular wurde nicht vollständig übertragen.', 'ps-pen-paper' ) ); }
	$character_id = ps_pen_paper_posted_id( 'character_id' );
	$profile_url = $character_id ? ps_pen_paper_profile_return( $id, $character_id ) : '';
	$revision = ps_pen_paper_posted_text( 'equipment_revision' );
	$input = isset( $_POST['equipment'] ) ? wp_unslash( $_POST['equipment'] ) : array();
	if ( 'save_item' === $operation ) {
		$result = ps_pen_paper_save_item( $scope, ps_pen_paper_posted_id( 'item_id' ), $input, $revision, ps_pen_paper_equipment_upload( 'equipment_image' ) );
	} elseif ( 'save_equipment_schema' === $operation ) {
		$result = ps_pen_paper_save_equipment_schema( $scope, $input, $revision );
	} elseif ( 'save_portrait' === $operation ) {
		$result = ps_pen_paper_save_portrait( $scope, $character_id, $revision, ps_pen_paper_posted_text( 'portrait_action' ), ps_pen_paper_equipment_upload( 'equipment_image' ) );
	} elseif ( 'save_sheet_style' === $operation ) {
		$action = ps_pen_paper_posted_text( 'sheet_action' );
		$path = ps_pen_paper_equipment_upload( 'equipment_image' );
		if ( ! in_array( $action, array( 'upload', 'remove' ), true ) || ( 'upload' === $action && null === $path ) || ( 'remove' === $action && null !== $path ) ) { ps_pen_paper_fail( __( 'Bitte wähle entweder ein neues Bild oder das Entfernen.', 'ps-pen-paper' ) ); }
		$result = ps_pen_paper_save_sheet_style( $scope, $revision, $path );
	} else {
		$result = 'pspp_campaign' === $scope->post_type ? ps_pen_paper_save_gear( $scope, $character_id, $operation, $input, $revision ) : ps_pen_paper_equipment_error();
	}
	if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
	if ( $character_id ) {
		$url = $profile_url;
		if ( ! $url ) { $url = ps_pen_paper_character_url( $id, $character_id, $page_id ); }
	} else {
		$url = 'pspp_universe' === $scope->post_type ? ps_pen_paper_universe_url( $id, $page_id ) : ( $page_id ? ps_pen_paper_game_url( $page_id, $id ) : ps_pen_paper_admin_url( $id ) );
		$url .= 'save_sheet_style' === $operation ? '#pspp-settings' : '#pspp-equipment';
	}
	wp_safe_redirect( $url );
	exit;
}

function ps_pen_paper_equipment_form_start( $scope, $operation, $revision, $page_id, $character_id = 0, $multipart = false ) {
	ps_pen_paper_form_start( $operation, 'pspp_campaign_' . $scope->ID, $scope->ID, $page_id, $multipart );
	echo '<input type="hidden" name="equipment_revision" value="' . esc_attr( $revision ) . '"><input type="hidden" name="character_id" value="' . esc_attr( $character_id ) . '"><input type="hidden" name="equipment_complete" value="complete">';
}

function ps_pen_paper_render_item_card( $item, $source_scope ) {
	echo '<article class="pspp-item-card"><h4>' . esc_html( $item['name'] ) . '</h4><p><span class="pspp-rarity" style="' . esc_attr( '--pspp-rarity:' . $item['color'] ) . '">' . esc_html( $item['rarity_label'] ) . '</span> · ' . esc_html( $item['kind'] ) . '</p>';
	echo '<p>' . esc_html( ps_pen_paper_item_bindings()[ ps_pen_paper_item_binding( $item ) ] ) . '</p>';
	if ( $item['asset_id'] ) {
		$image = ps_pen_paper_private_image_data( $source_scope, $item['asset_id'] );
		echo is_wp_error( $image ) ? '<p role="alert">' . esc_html( $image->get_error_message() ) . '</p>' : '<img class="pspp-item-image" src="' . esc_attr( $image ) . '" alt="' . esc_attr( $item['name'] ) . '" loading="lazy">';
	}
	echo '<p class="pspp-multiline">' . esc_html( $item['description'] ) . '</p><dl>';
	foreach ( $item['properties'] as $label => $value ) { echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>'; }
	foreach ( $item['bonuses'] as $field_id => $bonus ) {
		$fields = array_column( ps_pen_paper_get_rules( $source_scope->ID )['fields'], 'label', 'id' );
		echo '<dt>' . esc_html( isset( $fields[ $field_id ] ) ? $fields[ $field_id ] : $field_id ) . '</dt><dd>' . esc_html( ( $bonus > 0 ? '+' : '' ) . $bonus ) . '</dd>';
	}
	echo '</dl></article>';
}

function ps_pen_paper_render_equipment_catalogue( $scope, $page_id ) {
	if ( ! ps_pen_paper_can_manage_character_scope( $scope ) ) { return; }
	if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { echo '<p>' . esc_html( $gate->get_error_message() ) . '</p>'; return; }
	$schema = ps_pen_paper_equipment_schema( $scope );
	$catalogue = ps_pen_paper_item_catalogue( $scope );
	if ( is_wp_error( $schema ) || is_wp_error( $catalogue ) ) { echo '<p role="alert">' . esc_html( ( is_wp_error( $schema ) ? $schema : $catalogue )->get_error_message() ) . '</p>'; return; }
	?>
	<h2><?php esc_html_e( 'Gegenstände & Ausrüstung', 'ps-pen-paper' ); ?></h2>
	<p><?php esc_html_e( 'Vorlagen sind keine Inventargegenstände. Ausgabe erfolgt ausdrücklich am Charakter. Universumsvorlagen stehen zugeordneten Kampagnen zur Verfügung; sie gewähren keine Teilnahme- oder Handelsrechte.', 'ps-pen-paper' ); ?></p>
	<details class="pspp-fold"><summary><?php esc_html_e( 'Slots und Seltenheitsstufen konfigurieren', 'ps-pen-paper' ); ?></summary>
	<?php ps_pen_paper_equipment_form_start( $scope, 'save_equipment_schema', $schema['revision'], $page_id ); ?>
		<fieldset class="pspp-field"><legend><?php esc_html_e( 'Ausrüstungsslots', 'ps-pen-paper' ); ?></legend>
			<?php foreach ( ps_pen_paper_equipment_display_slots( $schema ) as $id => $entry ) : ?>
				<div class="pspp-slot-setting">
					<label><?php echo esc_html( $entry['label'] ); ?><input type="text" name="equipment[slot_rows][<?php echo esc_attr( $id ); ?>][label]" value="<?php echo esc_attr( $entry['label'] ); ?>" maxlength="200" required></label>
					<input type="hidden" name="equipment[slot_rows][<?php echo esc_attr( $id ); ?>][kind]" value="<?php echo esc_attr( $entry['kind'] ); ?>">
					<label class="pspp-check"><input type="checkbox" name="equipment[hidden_slots][<?php echo esc_attr( $id ); ?>]" value="hidden" <?php checked( ! empty( $entry['hidden'] ) ); ?>><?php esc_html_e( 'Ausblenden', 'ps-pen-paper' ); ?></label>
				</div>
			<?php endforeach; ?>
			<input type="hidden" name="equipment[slot_visibility_complete]" value="complete">
			<p><?php esc_html_e( 'Namen ändern oder „Ausblenden“ auswählen. Ausgeblendete Slots bleiben gespeichert; bereits angelegte Gegenstände und Boni bleiben unverändert.', 'ps-pen-paper' ); ?></p>
		</fieldset>
		<details><summary><?php esc_html_e( 'Weitere eigene Slots hinzufügen', 'ps-pen-paper' ); ?></summary>
			<label><?php esc_html_e( 'Kennung | Bezeichnung | Gegenstandstyp, eine Zeile pro neuem Slot', 'ps-pen-paper' ); ?><textarea name="equipment[new_slots]" rows="3" placeholder="ruecken | Rücken | rucksack"></textarea></label>
		</details>
		<label><?php esc_html_e( 'Seltenheiten: Kennung | Bezeichnung | #Farbe, eine Zeile pro Stufe', 'ps-pen-paper' ); ?><textarea name="equipment[rarities]" rows="4" placeholder="normal | Normal | #718096"><?php
			$lines = array(); foreach ( $schema['rarities'] as $id => $entry ) { $lines[] = $id . ' | ' . $entry['label'] . ' | ' . $entry['color']; } echo esc_textarea( implode( "\n", $lines ) );
		?></textarea></label>
		<p><?php esc_html_e( 'Kennungen und Typen: kleine lateinische Buchstaben, Ziffern, _ oder -. Bestehende Kennungen bleiben erhalten. Namen und Farben sind änderbar. Seltenheit erzeugt niemals automatisch Boni. Kampagnen konfigurieren ihre Slots selbst.', 'ps-pen-paper' ); ?></p>
		<button type="submit"><?php esc_html_e( 'Ausrüstungsvorlage speichern', 'ps-pen-paper' ); ?></button>
	</form></details>
	<?php foreach ( $catalogue as $item_id => $state ) :
		$parent = ps_pen_paper_get_character_scope( $state['scope_id'] );
		if ( ! $parent ) { echo '<p role="alert">' . esc_html__( 'Der Ursprung eines Gegenstands fehlt.', 'ps-pen-paper' ) . '</p>'; continue; }
		ps_pen_paper_render_item_card( $state['item'], $parent );
		if ( (int) $scope->ID === $state['scope_id'] ) { ps_pen_paper_render_item_form( $scope, $schema, $page_id, $item_id, $state ); }
	endforeach;
	ps_pen_paper_render_item_form( $scope, $schema, $page_id );
}

function ps_pen_paper_render_item_form( $scope, $schema, $page_id, $id = 0, $state = null ) {
	$item = $state ? $state['item'] : array( 'name' => '', 'description' => '', 'kind' => '', 'rarity' => '', 'properties' => array(), 'bonuses' => array() );
	?>
	<details class="pspp-fold"><summary><?php echo esc_html( $id ? __( 'Gegenstandsvorlage bearbeiten', 'ps-pen-paper' ) : __( 'Neue Gegenstandsvorlage', 'ps-pen-paper' ) ); ?></summary>
	<?php ps_pen_paper_equipment_form_start( $scope, 'save_item', $state ? $state['revision'] : 0, $page_id, 0, true ); ?>
		<input type="hidden" name="item_id" value="<?php echo esc_attr( $id ); ?>">
		<label><?php esc_html_e( 'Gegenstandsname', 'ps-pen-paper' ); ?><input type="text" name="equipment[name]" maxlength="200" value="<?php echo esc_attr( $item['name'] ); ?>" required></label>
		<label><?php esc_html_e( 'Beschreibung', 'ps-pen-paper' ); ?><textarea name="equipment[description]" rows="4"><?php echo esc_textarea( $item['description'] ); ?></textarea></label>
		<label><?php esc_html_e( 'Gegenstandstyp / passende Slotart', 'ps-pen-paper' ); ?><input type="text" name="equipment[kind]" pattern="[a-z][a-z0-9_-]{0,39}" value="<?php echo esc_attr( $item['kind'] ); ?>" required></label>
		<label><?php esc_html_e( 'Seltenheitsstufe', 'ps-pen-paper' ); ?><select name="equipment[rarity]" required><option value=""><?php esc_html_e( 'Bitte wählen', 'ps-pen-paper' ); ?></option>
		<?php foreach ( $schema['rarities'] as $key => $entry ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $item['rarity'], $key ); ?>><?php echo esc_html( $entry['label'] ); ?></option><?php endforeach; ?>
		</select></label>
		<label><?php esc_html_e( 'Gegenstandsbindung', 'ps-pen-paper' ); ?><select name="equipment[binding]" required><?php foreach ( ps_pen_paper_item_bindings() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( ps_pen_paper_item_binding( $item ), $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
		<p><?php esc_html_e( 'Die Bindung wird beim Ausgeben im Exemplar festgehalten. Vorlagenänderungen binden oder lösen keine bereits ausgegebenen Gegenstände. Ob und wie handelbare Exemplare übertragen werden dürfen, bestimmt die zuständige Spielleitung in den Handelsregeln.', 'ps-pen-paper' ); ?></p>
		<label><?php esc_html_e( 'Eigenschaften: Bezeichnung | Wert, eine Zeile pro Eigenschaft', 'ps-pen-paper' ); ?><textarea name="equipment[properties]" rows="3"><?php
			$lines = array(); foreach ( $item['properties'] as $key => $value ) { $lines[] = $key . ' | ' . $value; } echo esc_textarea( implode( "\n", $lines ) );
		?></textarea></label>
		<fieldset class="pspp-field"><legend><?php esc_html_e( 'Automatische additive Boni beim Anlegen', 'ps-pen-paper' ); ?></legend>
		<?php $bonus_rules = ps_pen_paper_get_rules( $scope->ID );
		foreach ( $bonus_rules['fields'] as $field ) :
			if ( ! in_array( $field['type'], array( 'number', 'attribute' ), true ) ||
				( ! empty( $bonus_rules['progression'] ) && in_array( $field['id'], array( $bonus_rules['progression']['experience_field'], $bonus_rules['progression']['level_field'] ), true ) ) ) { continue; } ?>
			<label><?php echo esc_html( $field['label'] ); ?><input type="number" min="-1000000" max="1000000" step="1" name="equipment[bonuses][<?php echo esc_attr( $field['id'] ); ?>]" value="<?php echo esc_attr( isset( $item['bonuses'][ $field['id'] ] ) ? $item['bonuses'][ $field['id'] ] : 0 ); ?>"></label>
		<?php endforeach; ?></fieldset>
		<label><?php esc_html_e( 'Gegenstandsgrafik hochladen / ersetzen', 'ps-pen-paper' ); ?><input type="file" name="equipment_image" accept="image/png,image/jpeg,image/webp"></label>
		<p><?php esc_html_e( 'Geschützte PNG-/JPEG-/WebP-Bilder bis 2 MiB und 3000 × 3000 Pixel. Kleine Grafiken bevorzugen. Änderungen gelten nur für spätere Ausgaben, nicht für vorhandene Exemplare.', 'ps-pen-paper' ); ?></p>
		<button type="submit"><?php esc_html_e( 'Gegenstandsvorlage speichern', 'ps-pen-paper' ); ?></button>
	</form></details>
	<?php
}

function ps_pen_paper_render_character_loadout( $campaign, $character, $name ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_get_character( $campaign, $character->ID ) ) {
		echo '<p role="alert">' . esc_html__( 'Du darfst diesen Charakter nicht ansehen.', 'ps-pen-paper' ) . '</p>';
		return;
	}
	if ( 'pspp_campaign' !== $campaign->post_type || ! ps_pen_paper_community_available() ) {
		ps_pen_paper_render_portrait( $campaign, $character, $name );
		return;
	}
	$gear = ps_pen_paper_gear_state( $character->ID );
	$schema = ps_pen_paper_equipment_schema( $campaign );
	if ( is_wp_error( $gear ) || is_wp_error( $schema ) ) {
		echo '<p role="alert">' . esc_html( ( is_wp_error( $gear ) ? $gear : $schema )->get_error_message() ) . '</p>';
		return;
	}
	$columns = array( 'left' => array(), 'right' => array() );
	$index = 0;
	foreach ( ps_pen_paper_equipment_display_slots( $schema ) as $id => $slot ) {
		if ( ! empty( $slot['hidden'] ) ) { continue; }
		$columns[ 0 === $index++ % 2 ? 'left' : 'right' ][ $id ] = $slot;
	}
	echo '<div class="pspp-character-loadout">';
	foreach ( $columns as $side => $slots ) {
		if ( 'right' === $side ) {
			echo '<div class="pspp-loadout-character">';
			$image = ps_pen_paper_portrait_image( $campaign, $character );
			if ( is_wp_error( $image ) ) { echo '<p role="alert">' . esc_html( $image->get_error_message() ) . '</p>'; }
			elseif ( $image ) { echo '<img class="pspp-loadout-avatar" src="' . esc_attr( $image ) . '" alt="' . esc_attr( $name ) . '">'; }
			else { echo '<div class="pspp-loadout-avatar pspp-loadout-avatar-empty" aria-hidden="true">' . esc_html( wp_html_excerpt( $name, 1, '' ) ) . '</div><p>' . esc_html__( 'Noch kein Charakterbild', 'ps-pen-paper' ) . '</p>'; }
			echo '<strong>' . esc_html( $name ) . '</strong></div>';
		}
		echo '<ul class="pspp-loadout-slots pspp-loadout-' . esc_attr( $side ) . '">';
		foreach ( $slots as $id => $slot ) {
			$instance_id = isset( $gear['active']['slots'][ $id ] ) ? $gear['active']['slots'][ $id ] : null;
			$instance = $instance_id ? $gear['active']['items'][ $instance_id ] : null;
			echo '<li class="pspp-loadout-slot" data-equipment-slot="' . esc_attr( $id ) . '"><strong>' . esc_html( $slot['label'] ) . '</strong>';
			$image = '';
			if ( $instance && $instance['item']['asset_id'] ) {
				$source = ps_pen_paper_get_character_scope( $instance['scope_id'] );
				$image = $source ? ps_pen_paper_private_image_data( $source, $instance['item']['asset_id'] ) : ps_pen_paper_equipment_error();
			}
			if ( is_wp_error( $image ) ) { echo '<p role="alert">' . esc_html( $image->get_error_message() ) . '</p>'; }
			elseif ( $image ) { echo '<img class="pspp-loadout-icon" src="' . esc_attr( $image ) . '" alt="' . esc_attr( $instance['item']['name'] ) . '" loading="lazy">'; }
			else { echo '<span class="pspp-loadout-icon pspp-loadout-icon-empty" aria-hidden="true"></span>'; }
			echo '<span>' . esc_html( $instance ? $instance['item']['name'] : __( 'Leer', 'ps-pen-paper' ) ) . '</span></li>';
		}
		echo '</ul>';
	}
	echo '</div>';
}

function ps_pen_paper_render_character_equipment( $campaign, $character, $page_id, $include_adventures = true ) {
	ps_pen_paper_render_wallet( $campaign, $character, $page_id );
	if ( 'pspp_campaign' !== $campaign->post_type ) { return; }
	if ( ! ps_pen_paper_community_available() ) {
		echo '<p>' . esc_html__( 'Inventar, Guthaben und Ausrüstung sind ohne PS Community inaktiv; gespeicherte Bestände bleiben erhalten.', 'ps-pen-paper' ) . '</p>';
		ps_pen_paper_render_character_trade( $campaign, $character, $page_id );
		ps_pen_paper_render_character_transfers( $campaign, $character, $page_id );
		if ( $include_adventures ) { ps_pen_paper_render_character_adventures( $campaign, $character, $page_id ); }
		return;
	}
	$gear = ps_pen_paper_gear_state( $character->ID );
	$schema = ps_pen_paper_equipment_schema( $campaign );
	if ( is_wp_error( $gear ) || is_wp_error( $schema ) ) { echo '<p role="alert">' . esc_html__( 'Die Ausrüstung ist nicht verfügbar. Bitte informiere die Spielleitung.', 'ps-pen-paper' ) . '</p>'; return; }
	$is_gm = ps_pen_paper_can_manage_campaign( $campaign );
	echo '<h3>' . esc_html__( 'Inventar & Ausrüstung', 'ps-pen-paper' ) . '</h3>';
	foreach ( $gear['active']['items'] as $instance ) {
		$source = ps_pen_paper_get_character_scope( $instance['scope_id'] );
		if ( ! $source ) { echo '<p role="alert">' . esc_html__( 'Der Gegenstandsursprung fehlt.', 'ps-pen-paper' ) . '</p>'; continue; }
		ps_pen_paper_render_item_card( $instance['item'], $source );
	}
	$can_edit = $is_gm || 'gm_only' !== ps_pen_paper_character_mode( ps_pen_paper_get_rules( $campaign->ID ) );
	if ( $can_edit ) {
		ps_pen_paper_equipment_form_start( $campaign, 'equip_items', $gear['revision'], $page_id, $character->ID );
		$snapshot = null !== $gear['pending'] ? $gear['pending'] : $gear['active'];
		foreach ( $schema['slots'] as $slot_id => $slot ) {
			if ( ! empty( $slot['hidden'] ) ) {
				echo '<input type="hidden" name="equipment[slots][' . esc_attr( $slot_id ) . ']" value="' . esc_attr( isset( $snapshot['slots'][ $slot_id ] ) ? $snapshot['slots'][ $slot_id ] : '' ) . '">';
				continue;
			}
			echo '<label>' . esc_html( $slot['label'] ) . '<select name="equipment[slots][' . esc_attr( $slot_id ) . ']"><option value="">' . esc_html__( 'Leer', 'ps-pen-paper' ) . '</option>';
			foreach ( $snapshot['items'] as $instance_id => $instance ) {
				if ( $instance['item']['kind'] !== $slot['kind'] ) { continue; }
				echo '<option value="' . esc_attr( $instance_id ) . '" ' . selected( isset( $snapshot['slots'][ $slot_id ] ) ? $snapshot['slots'][ $slot_id ] : '', $instance_id, false ) . '>' . esc_html( $instance['item']['name'] . ' · ' . substr( $instance_id, 0, 8 ) ) . '</option>';
			}
			echo '</select></label>';
		}
		echo '<p>' . esc_html__( 'Ein Exemplar belegt höchstens einen passenden Slot. Boni werden addiert; die erlaubten Feldgrenzen bleiben verbindlich. Im Freigabemodus bleibt bis zur Zustimmung die bisherige Belegung wirksam.', 'ps-pen-paper' ) . '</p><button type="submit">' . esc_html__( 'Ausrüstung speichern / einreichen', 'ps-pen-paper' ) . '</button></form>';
	}
	if ( null !== $gear['pending'] ) {
		echo '<p>' . esc_html__( 'Ausrüstungsfreigabe ausstehend. Die Auswahl oben zeigt den Antrag, nicht die wirksame Belegung.', 'ps-pen-paper' ) . '</p>';
		if ( $is_gm ) {
			ps_pen_paper_equipment_form_start( $campaign, 'review_gear', $gear['revision'], $page_id, $character->ID );
			echo '<button name="equipment[decision]" value="approve">' . esc_html__( 'Ausrüstung freigeben', 'ps-pen-paper' ) . '</button><button name="equipment[decision]" value="reject">' . esc_html__( 'Ausrüstungsantrag verwerfen', 'ps-pen-paper' ) . '</button></form>';
		}
	}
	echo '<dl class="pspp-equipped">';
	foreach ( $schema['slots'] as $id => $slot ) {
		if ( ! empty( $slot['hidden'] ) ) { continue; }
		$name = isset( $gear['active']['slots'][ $id ] ) ? $gear['active']['items'][ $gear['active']['slots'][ $id ] ]['item']['name'] : __( 'Leer', 'ps-pen-paper' );
		echo '<dt>' . esc_html( $slot['label'] ) . '</dt><dd>' . esc_html( $name ) . '</dd>';
	}
	echo '</dl>';
	if ( $include_adventures ) { ps_pen_paper_render_character_adventures( $campaign, $character, $page_id ); }
	ps_pen_paper_render_character_trade( $campaign, $character, $page_id );
	if ( $is_gm ) {
		if ( $gear['active']['items'] ) {
			echo '<details class="pspp-fold"><summary>' . esc_html__( 'Gegenstände entziehen / entfernen', 'ps-pen-paper' ) . '</summary>';
			ps_pen_paper_equipment_form_start( $campaign, 'remove_items', $gear['revision'], $page_id, $character->ID );
			foreach ( $gear['active']['items'] as $uuid => $instance ) {
				echo '<label class="pspp-check"><input type="checkbox" name="equipment[ids][]" value="' . esc_attr( $uuid ) . '">' .
					esc_html( $instance['item']['name'] . ' · ' . substr( $uuid, 0, 8 ) ) . '</label>';
			}
			echo '<p>' . esc_html__( 'Entfernt ausgewählte Exemplare aus Inventar, wirksamer Ausrüstung und offenem Ausrüstungsantrag. Reservierte Handelsgegenstände bleiben gesperrt. Frühere Vergaben bleiben im Protokoll erhalten.', 'ps-pen-paper' ) . '</p>';
			echo '<label class="pspp-check"><input type="checkbox" name="equipment[confirmation]" value="confirmed" required>' .
				esc_html__( 'Diese Exemplare verbindlich entfernen.', 'ps-pen-paper' ) . '</label><button type="submit">' .
				esc_html__( 'Ausgewählte Exemplare entfernen', 'ps-pen-paper' ) . '</button></form></details>';
		}
		$catalogue = ps_pen_paper_item_catalogue( $campaign );
		if ( is_wp_error( $catalogue ) ) { echo '<p role="alert">' . esc_html( $catalogue->get_error_message() ) . '</p>'; return; }
		foreach ( $catalogue as $id => $entry ) {
			ps_pen_paper_equipment_form_start( $campaign, 'grant_item', $gear['revision'], $page_id, $character->ID );
			echo '<input type="hidden" name="equipment[item_id]" value="' . esc_attr( $id ) . '"><input type="hidden" name="equipment[item_revision]" value="' . esc_attr( $entry['revision'] ) . '"><button type="submit">' . esc_html( __( 'Ein Exemplar ausgeben:', 'ps-pen-paper' ) . ' ' . $entry['item']['name'] ) . '</button></form>';
		}
	}
}

function ps_pen_paper_render_portrait_form( $campaign, $character, $page_id ) {
	if ( 'pspp_campaign' !== $campaign->post_type ) { return; }
	$state = ps_pen_paper_portrait_state( $character->ID );
	if ( is_wp_error( $state ) ) { echo '<p role="alert">' . esc_html( $state->get_error_message() ) . '</p>'; return; }
	echo '<h3>' . esc_html__( 'Charakterporträt', 'ps-pen-paper' ) . '</h3>';
	foreach ( array( 'active' => __( 'Freigegebenes Porträt', 'ps-pen-paper' ), 'pending' => __( 'Porträtantrag', 'ps-pen-paper' ) ) as $key => $label ) {
		$snapshot = $state[ $key ];
		if ( null === $snapshot ) { continue; }
		echo '<p>' . esc_html( $label ) . '</p>';
		if ( $snapshot['asset_id'] ) {
			$image = ps_pen_paper_private_image( $campaign, $snapshot['asset_id'] );
			echo is_wp_error( $image ) ? '<p role="alert">' . esc_html( $image->get_error_message() ) . '</p>' : '<img class="pspp-character-picture" src="' . esc_attr( $image ) . '" alt="' . esc_attr( $label ) . '">';
		} else { echo '<p>' . esc_html__( 'Ohne Bild', 'ps-pen-paper' ) . '</p>'; }
	}
	if ( ! ps_pen_paper_can_edit_portrait( $campaign, $character->ID ) ) { return; }
	ps_pen_paper_equipment_form_start( $campaign, 'save_portrait', $state['revision'], $page_id, $character->ID, true );
	echo '<input type="file" name="equipment_image" accept="image/png,image/jpeg,image/webp"><p>' . esc_html__( 'PNG, JPEG oder WebP bis 2 MiB und 3000 × 3000 Pixel. Privat für Besitzer und Spielleitung, kein öffentlicher Benutzeravatar. Spieleränderungen folgen der Charakter-Freigabepolitik; Wertefreigaben bleiben getrennt.', 'ps-pen-paper' ) . '</p>';
	foreach ( array( 'upload' => __( 'Porträt einreichen / speichern', 'ps-pen-paper' ), 'remove' => __( 'Porträt entfernen / beantragen', 'ps-pen-paper' ) ) as $key => $label ) { echo '<button name="portrait_action" value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</button>'; }
	if ( null !== $state['pending'] && ps_pen_paper_can_manage_campaign( $campaign ) ) {
		echo '<button name="portrait_action" value="approve">' . esc_html__( 'Porträt freigeben', 'ps-pen-paper' ) . '</button><button name="portrait_action" value="reject">' . esc_html__( 'Porträtantrag verwerfen', 'ps-pen-paper' ) . '</button>';
	}
	echo '</form>';
}

function ps_pen_paper_render_sheet_style_form( $campaign, $page_id ) {
	$style = ps_pen_paper_sheet_style( $campaign );
	if ( is_wp_error( $style ) ) { echo '<p role="alert">' . esc_html( $style->get_error_message() ) . '</p>'; return; }
	echo '<h3>' . esc_html__( 'Bogenhintergrund der Kampagne', 'ps-pen-paper' ) . '</h3>';
	ps_pen_paper_equipment_form_start( $campaign, 'save_sheet_style', $style['revision'], $page_id, 0, true );
	echo '<input type="file" name="equipment_image" accept="image/png,image/jpeg,image/webp"><p>' . esc_html__( 'Dekoratives Bild für alle eigenen Charakterbögen dieser Kampagne. Es erscheint dezent hinter den Werten, nicht im öffentlichen Profil. PNG/JPEG/WebP bis 2 MiB und 3000 × 3000 Pixel.', 'ps-pen-paper' ) . '</p><button name="sheet_action" value="upload">' . esc_html__( 'Bogenhintergrund speichern', 'ps-pen-paper' ) . '</button><button name="sheet_action" value="remove">' . esc_html__( 'Bogenhintergrund entfernen', 'ps-pen-paper' ) . '</button></form>';
}

function ps_pen_paper_render_equipment_history( $kind, $state ) {
	if ( 'equipment_schema' === $kind ) {
		foreach ( array( 'slots', 'rarities' ) as $key ) {
			echo '<ul>';
			foreach ( $state[ $key ] as $id => $entry ) { echo '<li>' . esc_html( $id . ' · ' . $entry['label'] . ' · ' . ( 'slots' === $key ? $entry['kind'] : $entry['color'] ) ) . '</li>'; }
			echo '</ul>';
		}
	} elseif ( 'sheet_style_saved' === $kind ) {
		echo '<p>' . esc_html( __( 'Geschütztes Hintergrundbild:', 'ps-pen-paper' ) . ' ' . $state['asset_id'] ) . '</p>';
	} elseif ( 'item_saved' === $kind ) {
		if ( $state['item'] ) {
			echo '<p>' . esc_html( ps_pen_paper_item_bindings()[ ps_pen_paper_item_binding( $state['item'] ) ] ) . '</p>';
			echo '<p>' . esc_html( $state['item']['name'] . ' · ' . $state['item']['kind'] . ' · ' . $state['item']['rarity_label'] ) . '</p><p class="pspp-multiline">' . esc_html( $state['item']['description'] ) . '</p><dl>';
			foreach ( $state['item']['properties'] as $label => $value ) { echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>'; }
			foreach ( $state['item']['bonuses'] as $id => $bonus ) { echo '<dt>' . esc_html( $id ) . '</dt><dd>' . esc_html( $bonus ) . '</dd>'; }
			echo '</dl><p>' . esc_html( __( 'Geschütztes Bild:', 'ps-pen-paper' ) . ' ' . $state['item']['asset_id'] ) . '</p>';
		} else { echo '<p>' . esc_html__( 'Noch keine Vorlage.', 'ps-pen-paper' ) . '</p>'; }
	} else {
		foreach ( array( 'active' => __( 'Gültiger Stand', 'ps-pen-paper' ), 'pending' => __( 'Antrag', 'ps-pen-paper' ) ) as $key => $label ) {
			echo '<h5>' . esc_html( $label ) . '</h5>';
			$snapshot = $state[ $key ];
			if ( null === $snapshot ) { echo '<p>' . esc_html__( 'Kein Antrag', 'ps-pen-paper' ) . '</p>'; continue; }
			if ( 'portrait_saved' === $kind ) { echo '<p>' . esc_html( __( 'Geschütztes Porträt:', 'ps-pen-paper' ) . ' ' . $snapshot['asset_id'] ) . '</p>'; continue; }
			echo '<ul>';
			foreach ( $snapshot['items'] as $id => $instance ) {
				echo '<li>' . esc_html( $instance['item']['name'] . ' · ' . $id . ' · ' . __( 'Vorlagenrevision:', 'ps-pen-paper' ) . ' ' . $instance['revision'] ) . '<ul>';
				echo '<li>' . esc_html( ps_pen_paper_item_bindings()[ ps_pen_paper_item_binding( $instance['item'] ) ] ) . '</li>';
				foreach ( $instance['item']['bonuses'] as $field_id => $bonus ) { echo '<li>' . esc_html( $field_id . ': ' . $bonus ) . '</li>'; }
				echo '</ul></li>';
			}
			foreach ( $snapshot['slots'] as $id => $instance_id ) { echo '<li>' . esc_html( $id . ': ' . $snapshot['items'][ $instance_id ]['item']['name'] ) . '</li>'; }
			echo '</ul>';
		}
	}
}
