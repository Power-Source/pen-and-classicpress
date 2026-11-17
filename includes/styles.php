<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_style_defaults() {
	return array(
		'background' => '#f1f5f9',
		'surface'    => '#ffffff',
		'text'       => '#172033',
		'primary'    => '#334155',
		'accent'     => '#1d4ed8',
		'border'     => '#64748b',
	);
}

function ps_pen_paper_validate_colors( $colors ) {
	$defaults = ps_pen_paper_style_defaults();
	if ( ! is_array( $colors ) || count( $colors ) !== count( $defaults ) || array_diff_key( $defaults, $colors ) ) {
		return new WP_Error( 'invalid_colors', __( 'Bitte gib für jede Farbe einen vollständigen Hex-Farbwert wie #334155 an.', 'ps-pen-paper' ) );
	}
	foreach ( $colors as $key => $color ) {
		if ( ! is_string( $color ) || ! preg_match( '/^#[0-9a-f]{6}$/iD', $color ) ) {
			return new WP_Error( 'invalid_colors', __( 'Erlaubt sind nur Hex-Farbwerte mit sechs Stellen, zum Beispiel #334155.', 'ps-pen-paper' ) );
		}
		$colors[ $key ] = strtolower( $color );
	}
	return $colors;
}

function ps_pen_paper_get_style( $campaign_id ) {
	$style = get_post_meta( $campaign_id, '_pspp_style', true );
	if ( '' === $style ) {
		return array( 'revision' => 0, 'colors' => ps_pen_paper_style_defaults() );
	}
	if ( ! is_array( $style ) || ! isset( $style['revision'], $style['colors'] ) || ! is_int( $style['revision'] ) || $style['revision'] < 1 ) {
		return new WP_Error( 'invalid_style', __( 'Die gespeicherte Gestaltung ist beschädigt. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
	}
	$colors = ps_pen_paper_validate_colors( $style['colors'] );
	return is_wp_error( $colors ) ? $colors : $style;
}

function ps_pen_paper_effective_style( $scope_id ) {
	$scope = ps_pen_paper_get_character_scope( $scope_id );
	if ( ! $scope ) { return new WP_Error( 'invalid_style', __( 'Die Welt für diese Gestaltung ist nicht verfügbar.', 'ps-pen-paper' ) ); }
	if ( 'pspp_campaign' === $scope->post_type ) {
		$link = ps_pen_paper_get_universe_link( $scope_id );
		if ( is_wp_error( $link ) ) { return $link; }
		if ( $link['universe_id'] ) {
			if ( ! ps_pen_paper_get_universe( $link['universe_id'] ) ) { return new WP_Error( 'invalid_style', __( 'Das zugeordnete Universum ist nicht verfügbar.', 'ps-pen-paper' ) ); }
			$scope_id = $link['universe_id'];
		}
	}
	return ps_pen_paper_get_style( $scope_id );
}

function ps_pen_paper_save_style( $campaign_id, $colors, $revision ) {
	$campaign = ps_pen_paper_get_character_scope( $campaign_id );
	if ( ! ps_pen_paper_can_access_site() || ! $campaign || ! ps_pen_paper_can_manage_character_scope( $campaign ) ) {
		return new WP_Error( 'forbidden', __( 'Nur die zuständige Spielleitung darf die Farben ändern.', 'ps-pen-paper' ) );
	}
	if ( 'pspp_campaign' === $campaign->post_type ) {
		$link = ps_pen_paper_get_universe_link( $campaign_id );
		if ( is_wp_error( $link ) ) { return $link; }
		if ( $link['universe_id'] ) { return new WP_Error( 'universe_style', __( 'Die Farben dieser Kampagne werden im zugeordneten Universum eingestellt.', 'ps-pen-paper' ) ); }
	}
	$previous = ps_pen_paper_get_style( $campaign_id );
	if ( is_wp_error( $previous ) ) {
		return $previous;
	}
	if ( ! is_string( $revision ) || (string) $previous['revision'] !== $revision ) {
		return new WP_Error( 'conflict', __( 'Die Gestaltung wurde inzwischen geändert. Lade die Seite neu.', 'ps-pen-paper' ) );
	}
	$colors = ps_pen_paper_validate_colors( $colors );
	if ( is_wp_error( $colors ) ) {
		return $colors;
	}
	$style = array( 'revision' => $previous['revision'] + 1, 'colors' => $colors );
	$saved = 0 === $previous['revision']
		? add_post_meta( $campaign_id, '_pspp_style', $style, true )
		: update_post_meta( $campaign_id, '_pspp_style', $style, $previous );
	if ( ! $saved ) {
		return new WP_Error( 'save_failed', __( 'Die Gestaltung konnte nicht gespeichert werden. Prüfe zwischenzeitliche Änderungen durch erneutes Laden.', 'ps-pen-paper' ) );
	}
	return $style;
}

function ps_pen_paper_luminance( $color ) {
	$channels = array();
	foreach ( array( 1, 3, 5 ) as $offset ) {
		$value = hexdec( substr( $color, $offset, 2 ) ) / 255;
		$channels[] = $value <= 0.04045 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
	}
	return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

function ps_pen_paper_contrast( $first, $second ) {
	$a = ps_pen_paper_luminance( $first );
	$b = ps_pen_paper_luminance( $second );
	return ( max( $a, $b ) + 0.05 ) / ( min( $a, $b ) + 0.05 );
}

function ps_pen_paper_style_variables( $colors ) {
	$variables = array();
	foreach ( ps_pen_paper_style_defaults() as $key => $default ) {
		$variables[] = '--pspp-' . $key . ':' . $colors[ $key ];
	}
	$button_text = ps_pen_paper_contrast( $colors['primary'], '#ffffff' ) >= ps_pen_paper_contrast( $colors['primary'], '#000000' ) ? '#ffffff' : '#000000';
	$variables[] = '--pspp-primary-text:' . $button_text;
	return implode( ';', $variables );
}

function ps_pen_paper_render_style_settings( $campaign, $page_id = 0 ) {
	if ( 'pspp_campaign' === $campaign->post_type ) {
		$link = ps_pen_paper_get_universe_link( $campaign->ID );
		if ( is_wp_error( $link ) ) { echo '<p role="alert">' . esc_html( $link->get_error_message() ) . '</p>'; return; }
		if ( $link['universe_id'] ) {
			echo '<p>' . esc_html__( 'Diese Kampagne übernimmt die UI-Farben ihres Universums. Änderungen nimmt die Universumsleitung dort vor.', 'ps-pen-paper' ) . '</p>';
			$universe = ps_pen_paper_get_universe( $link['universe_id'] );
			if ( $universe && ps_pen_paper_can_manage_universe( $universe ) ) {
				echo '<p><a href="' . esc_url( ps_pen_paper_dashboard_url( 'universes', $page_id ) ) . '">' . esc_html__( 'Universumsfarben einstellen', 'ps-pen-paper' ) . '</a></p>';
			}
			return;
		}
	}
	$style = ps_pen_paper_get_style( $campaign->ID );
	if ( is_wp_error( $style ) ) {
		ps_pen_paper_fail( $style->get_error_message() );
	}
	$labels = array(
		'background' => __( 'Hintergrund', 'ps-pen-paper' ),
		'surface'    => __( 'Flächen und Karten', 'ps-pen-paper' ),
		'text'       => __( 'Text', 'ps-pen-paper' ),
		'primary'    => __( 'Schaltflächen', 'ps-pen-paper' ),
		'accent'     => __( 'Links und Akzente', 'ps-pen-paper' ),
		'border'     => __( 'Rahmen', 'ps-pen-paper' ),
	);
	$prefix = 'pspp-style-' . $campaign->ID;
	?>
	<section id="<?php echo esc_attr( $prefix ); ?>" class="pspp-rules" aria-labelledby="<?php echo esc_attr( $prefix . '-heading' ); ?>">
		<h2 id="<?php echo esc_attr( $prefix . '-heading' ); ?>"><?php esc_html_e( 'UI-Farben', 'ps-pen-paper' ); ?></h2>
		<p><?php echo esc_html( 'pspp_universe' === $campaign->post_type ? __( 'Optional: Diese Farben gelten für die Kampagnen und Charakteransichten dieses Universums, auch im Profil. Ohne eigene Auswahl gelten die Standardfarben.', 'ps-pen-paper' ) : __( 'Diese Farben gelten für diese noch nicht zugeordnete Kampagne. Nach der Universumszuordnung werden die Universumsfarben verwendet.', 'ps-pen-paper' ) ); ?></p>
		<?php ps_pen_paper_form_start( 'pspp_universe' === $campaign->post_type ? 'save_universe_style' : 'save_style', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
			<div data-style-editor>
				<input type="hidden" name="style_revision" value="<?php echo esc_attr( $style['revision'] ); ?>">
				<div class="pspp-grid">
					<?php foreach ( $labels as $key => $label ) : ?>
						<div data-style-color="<?php echo esc_attr( $key ); ?>" data-default="<?php echo esc_attr( ps_pen_paper_style_defaults()[ $key ] ); ?>">
							<label for="<?php echo esc_attr( $prefix . '-' . $key ); ?>"><?php echo esc_html( $label ); ?></label>
							<div class="pspp-color-inputs">
								<input type="color" value="<?php echo esc_attr( $style['colors'][ $key ] ); ?>" aria-label="<?php echo esc_attr( sprintf( __( '%s: Farbwähler', 'ps-pen-paper' ), $label ) ); ?>" data-color-picker hidden>
								<input id="<?php echo esc_attr( $prefix . '-' . $key ); ?>" type="text" name="style_colors[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $style['colors'][ $key ] ); ?>" pattern="#[0-9a-fA-F]{6}" maxlength="7" placeholder="#334155" spellcheck="false" aria-describedby="<?php echo esc_attr( $prefix . '-help' ); ?>" required data-color-value>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<p id="<?php echo esc_attr( $prefix . '-help' ); ?>"><?php esc_html_e( 'Sechsstellige Hex-Farben verwenden. Die Schriftfarbe auf Schaltflächen wird automatisch schwarz oder weiß gewählt.', 'ps-pen-paper' ); ?></p>
				<button type="button" class="button" data-style-defaults hidden><?php esc_html_e( 'Standardfarben laden', 'ps-pen-paper' ); ?></button>
				<p role="alert" data-style-error hidden><?php esc_html_e( 'Bitte korrigiere die Farbwerte. Die Vorschau zeigt noch die letzte gültige Kombination.', 'ps-pen-paper' ); ?></p>
				<h3><?php esc_html_e( 'Live-Vorschau', 'ps-pen-paper' ); ?></h3>
				<div class="pspp-game pspp-style-preview" style="<?php echo esc_attr( ps_pen_paper_style_variables( $style['colors'] ) ); ?>" data-style-preview>
					<h3><?php echo esc_html( $campaign->post_title ); ?></h3>
					<p><?php esc_html_e( 'So wirken Hintergrund und Text deines Spielbereichs.', 'ps-pen-paper' ); ?></p>
					<div class="pspp-card">
						<h4><?php esc_html_e( 'Charakterbogen', 'ps-pen-paper' ); ?></h4>
						<p><?php esc_html_e( 'Beispieltext auf einer Inhaltsfläche.', 'ps-pen-paper' ); ?></p>
						<a href="#<?php echo esc_attr( $prefix . '-heading' ); ?>"><?php esc_html_e( 'Beispiellink', 'ps-pen-paper' ); ?></a>
						<button type="button" class="button button-primary"><?php esc_html_e( 'Beispielaktion', 'ps-pen-paper' ); ?></button>
					</div>
				</div>
				<div role="status" aria-live="polite" data-contrast-status data-good="<?php esc_attr_e( 'ausreichend', 'ps-pen-paper' ); ?>" data-warning="<?php esc_attr_e( 'geringer Kontrast', 'ps-pen-paper' ); ?>">
					<?php foreach ( array(
						'text background' => __( 'Text auf Hintergrund', 'ps-pen-paper' ),
						'text surface'    => __( 'Text auf Inhaltsflächen', 'ps-pen-paper' ),
						'accent background' => __( 'Links auf Hintergrund', 'ps-pen-paper' ),
						'accent surface'  => __( 'Links auf Inhaltsflächen', 'ps-pen-paper' ),
					) as $pair => $label ) :
						$keys = explode( ' ', $pair );
						$ratio = ps_pen_paper_contrast( $style['colors'][ $keys[0] ], $style['colors'][ $keys[1] ] );
						?>
						<p data-contrast-pair="<?php echo esc_attr( $pair ); ?>" data-label="<?php echo esc_attr( $label ); ?>"><?php echo esc_html( $label . ': ' . number_format_i18n( $ratio, 2 ) . ':1' . ( $ratio < 4.5 ? ' – ' . __( 'geringer Kontrast', 'ps-pen-paper' ) : '' ) ); ?></p>
					<?php endforeach; ?>
				</div>
				<p class="description"><?php esc_html_e( 'Richtwert für normalen Text: mindestens 4,5:1. Hinweise verhindern das Speichern nicht und ersetzen keine vollständige Barrierefreiheitsprüfung.', 'ps-pen-paper' ); ?></p>
				<input type="hidden" name="style_complete" value="complete">
				<?php submit_button( __( 'Gestaltung speichern', 'ps-pen-paper' ) ); ?>
			</div>
		</form>
	</section>
	<?php
}

function ps_pen_paper_handle_universe_style_action() {
	$id = ps_pen_paper_posted_id( 'campaign_id' );
	$page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
	check_admin_referer( 'pspp_campaign_' . $id );
	if ( ! ps_pen_paper_get_universe( $id ) || ( isset( $_POST['pspp_frontend_page'] ) && ! ps_pen_paper_frontend_page( $page_id ) ) ) {
		ps_pen_paper_fail( __( 'Diese Universumsverwaltung ist nicht verfügbar.', 'ps-pen-paper' ) );
	}
	if ( 'complete' !== ps_pen_paper_posted_text( 'style_complete' ) ) { ps_pen_paper_fail( __( 'Das Formular wurde nicht vollständig übertragen.', 'ps-pen-paper' ) ); }
	$result = ps_pen_paper_save_style( $id, isset( $_POST['style_colors'] ) ? wp_unslash( $_POST['style_colors'] ) : null, ps_pen_paper_posted_text( 'style_revision' ) );
	if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
	$return_url = ps_pen_paper_settings_return_url( $id, $page_id, 'style' );
	wp_safe_redirect( add_query_arg( 'pspp_notice', 'style_saved', $return_url ?: ps_pen_paper_dashboard_url( 'universes', $page_id ) ) );
	exit;
}
