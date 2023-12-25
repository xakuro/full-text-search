<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Full-Text Search admin.
 *
 * @package full-text-search
 */

/**
 * Full-Text Search admin class.
 */
class Full_Text_Search_Admin {
	/**
	 * Full_Text_Search instance.
	 *
	 * @var Full_Text_Search
	 */
	private $parent;

	/**
	 * Setting page id.
	 *
	 * @var string
	 */
	private $settings_page_id;

	/**
	 * Construction.
	 *
	 * @since 1.0.0
	 *
	 * @param XO_Event_Calendar $parent_object Parent object.
	 */
	public function __construct( $parent_object ) {
		$this->parent = $parent_object;
		add_action( 'plugins_loaded', array( $this, 'setup' ) );
	}

	/**
	 * Set up processing in the administration panel.
	 *
	 * @since 1.0.0
	 */
	public function setup() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'wp_ajax_full_text_search_settings', array( $this, 'ajax_full_text_search_settings' ) );
		add_filter( 'manage_media_columns', array( $this, 'manage_media_columns' ) );
		add_filter( 'manage_media_custom_column', array( $this, 'manage_media_custom_column' ), 10, 2 );
		add_filter( 'attachment_fields_to_edit', array( $this, 'attachment_fields_to_edit' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'attachment_fields_to_save' ), 10, 2 );
		add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );
	}

	/**
	 * Enqueue styles and scripts in the administration panel.
	 *
	 * @since 1.0.0
	 *
	 * @param int $hook_suffix Hook suffix for the current admin page.
	 */
	public function admin_enqueue_scripts( $hook_suffix ) {
		if ( $this->settings_page_id === $hook_suffix ) {
			wp_enqueue_style( 'full-text-search-settings', plugins_url( '/admin-settings.css', __FILE__ ), false, FULL_TEXT_SEARCH_VERSION );
			wp_enqueue_script( 'full-text-search-settings', plugins_url( '/admin-settings.js', __FILE__ ), array( 'jquery' ), FULL_TEXT_SEARCH_VERSION, false );

			$options = array(
				'nonce'                 => wp_create_nonce( 'full-text-search-settings' ),
				'completeMessageText'   => __( 'Indexing is complete.', 'full-text-search' ),
				'incompleteMessageText' => __( 'The index is incomplete. Please resynchronize.', 'full-text-search' ),
				'indexingText'          => __( 'Indexing ...', 'full-text-search' ),
				'incompleteText'        => __( 'Index incomplete', 'full-text-search' ),
				'goodText'              => __( 'Good', 'full-text-search' ),
				'totalPosts'            => 0,
				'indexedPosts'          => 0,
				'indexing'              => false,
			);
			wp_localize_script( 'full-text-search-settings', 'fullTextSearchSettingsOptions', $options );
		} elseif ( 'post.php' === $hook_suffix ) {
			$style = '
				#post-body-content table.compat-attachment-fields {
					width: 100%;
				}
				#post-body-content table.compat-attachment-fields th,
				#post-body-content table.compat-attachment-fields td {
					display: block;
					width: 100%;
				}
				#post-body-content table.compat-attachment-fields textarea {
					width: 100%;
					height: 16em;
				}
			';
			wp_add_inline_style( 'imgareaselect', $style );
		}
	}

	/**
	 * Add a menu to the administration panel.
	 *
	 * @since 1.0.0
	 */
	public function admin_menu() {
		$this->settings_page_id = add_options_page(
			__( 'Full-Text Search Settings', 'full-text-search' ),
			_x( 'Full-Text Search', 'setting', 'full-text-search' ),
			'manage_options',
			'full-text-search',
			array( $this, 'settings_page' )
		);

		add_action( "load-{$this->settings_page_id}", array( $this, 'add_settings_page_tabs' ) );
	}

	/**
	 * Add a tab to the setting page.
	 *
	 * @since 2.3.0
	 */
	public function add_settings_page_tabs() {
		$screen = get_current_screen();
		$screen->add_help_tab(
			array(
				'id'      => 'overview',
				'title'   => __( 'Overview', 'full-text-search' ),
				'content' => '<p>' . __( 'This screen is used to set up and maintain a full-text search.', 'full-text-search' ) . '</p>',
			)
		);
	}

	/**
	 * Set the featured image in AJAX.
	 *
	 * @since 1.0.0
	 */
	public function ajax_full_text_search_settings() {
		global $wpdb;

		check_ajax_referer( 'full-text-search-settings', 'nonce' );

		$res = array(
			'status'  => 'ERROR',
			'message' => '',
		);

		set_time_limit( 600 );
		header( 'Content-type: application/json' );

		try {
			$post_types = get_post_types( array( 'exclude_from_search' => false ) );
			$sql_posts  = "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";

			// phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
			$posts_count = $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type IN ({$sql_posts}) AND post_status <> 'auto-draft';" );

			// phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
			$index_count = $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->prefix}full_text_search_posts;" );

			$res['posts_count'] = $posts_count;
			$res['index_count'] = $index_count;

			if ( $posts_count === $index_count ) {
				$res['status'] = 'DONE';
			} else {
				$res['status'] = 'PROCESSING';
				if ( ! wp_get_schedule( 'full_text_search_event' ) ) {
					wp_schedule_single_event( time() + 1, 'full_text_search_event' );
				}
			}
		} catch ( Exception $e ) {
			$res['status']  = 'ERROR';
			$res['message'] = $e->getMessage();
		}

		echo wp_json_encode( $res );

		exit;
	}

	/**
	 * Output the settings page.
	 *
	 * @since 1.0.0
	 */
	public function settings_page() {
		global $wpdb;

		$tabs = array(
			''      => __( 'Settings', 'full-text-search' ),
			'info'  => __( 'Info', 'full-text-search' ),
			'maint' => __( 'Maintenance', 'full-text-search' ),
		);

		$wrapper_classes = array(
			'full-text-search-settings-tabs-wrapper',
			'hide-if-no-js',
			'tab-count-' . count( $tabs ),
		);

		$current_tab = ( isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '' );

		$post_types = get_post_types( array( 'exclude_from_search' => false ) );
		$sql_posts  = "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$posts_count = $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type IN ({$sql_posts}) AND post_status <> 'auto-draft';" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$index_count = $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->prefix}full_text_search_posts;" );

		$completed = ( $posts_count === $index_count );

		if ( isset( $_POST['full-text-search-index-sync'] ) || isset( $_POST['full-text-search-index-regenerate'] ) ) {
			check_admin_referer( 'full-text-search-settings' );

			if ( ! empty( $_POST['full-text-search-index-regenerate'] ) ) {
				$wpdb->query( "DELETE FROM {$wpdb->prefix}full_text_search_posts;" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query( "OPTIMIZE TABLE {$wpdb->prefix}full_text_search_posts;" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			}

			if ( ! wp_get_schedule( 'full_text_search_event' ) ) {
				wp_schedule_single_event( time() + 1, 'full_text_search_event' );
			}

			$completed = false;
		}

		$disabled = ( false !== wp_next_scheduled( 'full_text_search_event' ) );

		$stroke_dashoffse = $completed ? 'style="stroke-dashoffset: 0px;"' : '';
		$progress_state   = $completed ? 'green' : 'loading';
		$progress_text    = $disabled ? __( 'Indexing ...', 'full-text-search' ) : ( $completed ? __( 'Good', 'full-text-search' ) : __( 'Index incomplete', 'full-text-search' ) );

		?>
		<div class="full-text-search-settings-header">
			<div class="full-text-search-settings-title-section"><h1><?php echo esc_html_x( 'Full-Text Search', 'setting', 'full-text-search' ); ?></h1></div>

			<div class="full-text-search-settings-title-section full-text-search-settings-progress-wrapper <?php echo esc_attr( $progress_state ); ?>">
				<div class="full-text-search-settings-progress">
					<svg role="img" aria-hidden="true" focusable="false" width="100%" height="100%" viewBox="0 0 200 200" version="1.1" xmlns="http://www.w3.org/2000/svg">
						<circle r="90" cx="100" cy="100" fill="transparent" stroke-dasharray="565.48" stroke-dashoffset="0"></circle>
						<circle id="bar" r="90" cx="100" cy="100" fill="transparent" stroke-dasharray="565.48" stroke-dashoffset="0" <?php echo esc_attr( $stroke_dashoffse ); ?>></circle>
					</svg>
				</div>
				<div class="full-text-search-settings-progress-label"><?php echo esc_html( $progress_text ); ?></div>
			</div>

			<nav class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>" aria-label="<?php esc_attr_e( 'Secondary menu', 'full-text-search' ); ?>">
			<?php
			foreach ( $tabs as $slug => $label ) {
				printf(
					'<a href="%s" class="full-text-search-settings-tab %s">%s</a>',
					esc_url(
						add_query_arg(
							array(
								'page' => 'full-text-search',
								'tab'  => $slug,
							),
							admin_url( 'options-general.php' )
						)
					),
					( $current_tab === $slug ? 'active' : '' ),
					esc_html( $label )
				);
			}
			?>
			</nav>
		</div>
		<hr class="wp-header-end">
		<?php

		if ( isset( $_POST['full-text-search-delete-attachment-text'] ) ) {
			check_admin_referer( 'full-text-search-settings' );

			if ( ! empty( $_POST['full-text-search-delete-attachment-text'] ) ) {
				$result = true;

				$ids = get_posts(
					array(
						'post_type'      => 'attachment',
						'posts_per_page' => -1,
						'post_status'    => 'any',
						'fields'         => 'ids',
					)
				);

				if ( $ids ) {
					$ids = array_filter( $ids, 'is_numeric' );

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$result = $wpdb->query(
						$wpdb->prepare(
							"DELETE FROM $wpdb->postmeta WHERE meta_key = %s AND post_id IN (" . esc_sql( implode( ',', $ids ) ) . ')',
							Full_Text_Search::CUSTOM_FIELD_NAME
						)
					);
				}

				if ( false !== $result ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$result = $wpdb->query( "UPDATE {$wpdb->prefix}full_text_search_posts SET keywords = NULL, status = 0 WHERE post_type = 'attachment';" );
				}

				if ( false === $result ) {
					echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Failed to delete search text.', 'full-text-search' ) . '</strong></p></div>';
				} else {
					echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__( 'Deleted search text.', 'full-text-search' ) . '</strong></p></div>';
				}
			}
		}

		echo '<div class="notice notice-error hide-if-js"><p>' . esc_html__( 'The Full-Text Search Settings require JavaScript.', 'full-text-search' ) . '</p></div>';
		echo '<div class="full-text-search-settings-body hide-if-no-js">';

		if ( '' === $current_tab ) {
			echo '<form method="post" action="options.php">';
			settings_fields( 'full_text_search_group' );
			do_settings_sections( 'full_text_search_group' );
			submit_button();
			echo '</form>';
		} elseif ( 'info' === $current_tab ) {
			echo '<h2>' . esc_html__( 'Database information', 'full-text-search' ) . '</h2>';
			printf( '<p>%s: %s</p>', esc_html__( 'Database', 'full-text-search' ), ( 'mysql' === $this->parent->options['db_type'] ? 'MySQL' : 'MariaDB' ) );
			printf( '<p>%s: %s</p>', esc_html__( 'Database engine', 'full-text-search' ), ( 'mroonga' === $this->parent->options['db_engine'] ? 'Mroonga' : 'InnoDB' ) );

			if ( 'innodb' === $this->parent->options['db_engine'] ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$rows = $wpdb->get_row( "show variables like 'ngram_token_size';", ARRAY_A );

				$ngram_token_size = isset( $rows['Value'] ) ? $rows['Value'] : '';
				echo '<p>ngram_token_size: ' . esc_html( $ngram_token_size ) . '</p>';
			}
		} elseif ( 'maint' === $current_tab ) {
			echo '<div id="full-text-search-settings-maint">';

			echo '<h2>' . esc_html__( 'Full-text search index', 'full-text-search' ) . '</h2>';
			echo '<p>' . esc_html__( 'Number of search targets:', 'full-text-search' ) . ' <span class="posts-count">' . esc_html( $posts_count ) . '</span></p>';
			echo '<p>' . esc_html__( 'Number of indexes:', 'full-text-search' ) . ' <span class="index-count">' . esc_html( $index_count ) . '</span></p>';

			if ( $disabled ) {
				echo '<p class="message">' . esc_html__( 'Indexing ...', 'full-text-search' ) . ' <span class="full-text-search-settings-wp-paths-sizes spinner" style="visibility: visible;"></span></p>';
			} elseif ( $completed ) {
				echo '<p class="message">' . esc_html__( 'Indexing is complete.', 'full-text-search' ) . '</p>';
			} else {
				echo '<p class="message">' . esc_html__( 'The index is incomplete. Please resynchronize.', 'full-text-search' ) . '</p>';
			}

			echo '<form method="post" action="' . esc_url( admin_url( 'options-general.php?page=full-text-search&tab=maint' ) ) . '">';

			wp_nonce_field( 'full-text-search-settings' );

			echo '<p><input type="submit" class="button button-primary" name="full-text-search-index-sync" id="full-text-search-index-sync" value="' .
				esc_html__( 'Resync', 'full-text-search' ) . '"' . ( $disabled ? ' disabled=""' : '' ) . '/></p>';

			echo '<p><label for="regenerate-check"><input name="regenerate-check" type="checkbox" id="regenerate-check" value="1" onchange="document.getElementById(\'full-text-search-index-regenerate\').disabled = !this.checked;">' .
				esc_html__( 'Delete all indexes and regenerate.', 'full-text-search' ) . '</label>';
			echo '<p><input type="submit" class="button button-secondary" name="full-text-search-index-regenerate" id="full-text-search-index-regenerate" value="' .
				esc_html__( 'Regeneration', 'full-text-search' ) . '" disabled /></p>';

			echo '<p><label for="delete-attachment-text-check"><input name="delete-attachment-text-check" type="checkbox" id="delete-attachment-text-check" value="1" onchange="document.getElementById(\'full-text-search-delete-attachment-text\').disabled = !this.checked;">' .
				esc_html__( 'Delete search text of all attachments.', 'full-text-search' ) . '</label>';
			printf(
				'<p><input type="submit" class="button button-danger" name="full-text-search-delete-attachment-text" id="full-text-search-delete-attachment-text" value="%s" disabled onclick="return confirm( \'%s\' );" /></p>',
				esc_attr( __( 'Delete', 'full-text-search' ) ),
				esc_js( __( "The search text of all attachments will be deleted.\nThis action cannot be undone.\nClick 'Cancel' to go back, 'OK' to confirm the delete.", 'full-text-search' ) )
			);

			echo '</form>';

			if ( $disabled ) {
				echo '<script>new fullTextSearchSettings();</script>' . "\n";
			}

			echo '</div>'; // #full-text-search-settings-maint
		}

		echo '</div>' . "\n"; // .full-text-search-settings-body
	}

	/**
	 * Register the settings.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		register_setting( 'full_text_search_group', 'full_text_search_options', array( $this, 'sanitize' ) );

		add_settings_section( 'full_text_search_search_section', __( 'Full-Text Search Settings', 'full-text-search' ), '__return_empty_string', 'full_text_search_group' );
		add_settings_field( 'Enable full-text search', __( 'Full-text search', 'full-text-search' ), array( $this, 'field_enable_mode' ), 'full_text_search_group', 'full_text_search_search_section' );
		add_settings_field( 'Sort order', __( 'Sort order', 'full-text-search' ), array( $this, 'field_sort_order' ), 'full_text_search_group', 'full_text_search_search_section' );
		add_settings_field( 'Search result', __( 'Search result', 'full-text-search' ), array( $this, 'field_display_search_result' ), 'full_text_search_group', 'full_text_search_search_section' );
		add_settings_field( 'Search target', __( 'Search target', 'full-text-search' ), array( $this, 'field_search_target' ), 'full_text_search_group', 'full_text_search_search_section' );
		add_settings_field( 'Enable attachment search', __( 'Attachment search', 'full-text-search' ), array( $this, 'field_enable_attachment' ), 'full_text_search_group', 'full_text_search_search_section' );
		add_settings_field( 'Enable auto text', __( 'Automatic text extraction', 'full-text-search' ), array( $this, 'field_enable_auto_text' ), 'full_text_search_group', 'full_text_search_search_section' );
	}

	/**
	 * Register Enable full-text search field.
	 *
	 * @since 1.3.0
	 */
	public function field_enable_mode() {
		$enable_mode = $this->parent->options['enable_mode'] ?? 'enable';

		echo '<select id="field_enable_mode" name="full_text_search_options[enable_mode]">';
		echo '<option value="disable"' . ( 'disable' === $enable_mode ? ' selected' : '' ) . '>' . esc_html__( 'Disable', 'full-text-search' ) . '</option>';
		echo '<option value="enable"' . ( 'enable' === $enable_mode ? ' selected' : '' ) . '>' . esc_html__( 'Enable', 'full-text-search' ) . '</option>';
		echo '<option value="search"' . ( 'search' === $enable_mode ? ' selected' : '' ) . '>' . esc_html__( 'Enable on search page only', 'full-text-search' ) . '</option>';
		echo '</select>';
	}

	/**
	 * Register Sort order field.
	 *
	 * @since 2.3.0
	 */
	public function field_sort_order() {
		$sort_order = $this->parent->options['sort_order'] ?? 'score';

		echo '<select id="field_sort_order" name="full_text_search_options[sort_order]">';
		echo '<option value="default"' . ( 'default' === $sort_order ? ' selected' : '' ) . '>' . esc_html__( 'Default', 'full-text-search' ) . '</option>';
		echo '<option value="score"' . ( 'score' === $sort_order ? ' selected' : '' ) . '>' . esc_html__( 'Search score (Similarity)', 'full-text-search' ) . '</option>';
		echo '</select>';
	}

	/**
	 * Register Search result field.
	 *
	 * @since 2.4.0
	 * @since 2.9.0 Add search keyword highlight field.
	 */
	public function field_display_search_result() {
		$search_result_content = $this->parent->options['search_result_content'] ?? 'excerpt';
		$display_score         = $this->parent->options['display_score'] ?? false;
		$highlight             = $this->parent->options['highlight'] ?? false;
		$markjs                = $this->parent->options['markjs'] ?? false;

		echo '<fieldset id="display_search_result"><legend class="screen-reader-text"><span>' . esc_html__( 'Search result', 'full-text-search' ) . '</span></legend>';
		echo '<ul>';
		echo '<li><label for="display_score"><input type="checkbox" name="full_text_search_options[display_score]" id="display_score" value="1" ' . checked( $display_score, true, false ) . '> ' . esc_html__( 'Search Score', 'full-text-search' ) . '</label></li>';
		echo '<li><label for="highlight"><input type="checkbox" name="full_text_search_options[highlight]" id="highlight" value="1" ' . checked( $highlight, true, false ) . '> ' . esc_html__( 'Highlight search terms', 'full-text-search' ) . '</label></li>';
		echo '<ul>';
		echo '<li><label for="markjs"><input type="checkbox" name="full_text_search_options[markjs]" id="markjs" value="1" ' . checked( $markjs, true, false ) . '> ' . esc_html__( 'Use mark.js for highlighting', 'full-text-search' ) . '</label></li>';
		echo '</ul>';
		echo '<li><label for="field_search_result_content">' . esc_html__( 'Search result content:', 'full-text-search' ) . ' ';
		echo '<select id="field_search_result_content" name="full_text_search_options[search_result_content]">';
		echo '<option value="excerpt"' . ( 'excerpt' === $search_result_content ? ' selected' : '' ) . '>' . esc_html__( 'Excerpt', 'full-text-search' ) . '</option>';
		echo '<option value="content"' . ( 'content' === $search_result_content ? ' selected' : '' ) . '>' . esc_html__( 'Content', 'full-text-search' ) . '</option>';
		echo '</select></label></li>';
		echo '</ul>';
		echo '</fieldset>';
		echo '<p class="description">' . esc_html__( 'Choose according to the theme.', 'full-text-search' ) . '</p>';
	}

	/**
	 * Register Enable attachment search field.
	 *
	 * @since 1.6.0
	 */
	public function field_enable_attachment() {
		$enable_attachment = $this->parent->options['enable_attachment'] ?? 'enable';
		$zip               = $this->parent->options['enable_zip'] ?? false;
		$pdf               = $this->parent->options['enable_pdf'] ?? false;
		$word              = $this->parent->options['enable_word'] ?? false;
		$excel             = $this->parent->options['enable_excel'] ?? false;
		$powerpoint        = $this->parent->options['enable_powerpoint'] ?? false;

		echo '<fieldset id="enable-attachment"><legend class="screen-reader-text"><span>' . esc_html__( 'Attachment search', 'full-text-search' ) . '</span></legend>';

		echo '<p><label><input name="full_text_search_options[enable_attachment]" type="radio" value="disable" ' . checked( $enable_attachment, 'disable', false ) . ' />' . esc_html__( 'Disable', 'full-text-search' ) . '</label></p>';
		echo '<p><label><input name="full_text_search_options[enable_attachment]" type="radio" value="enable" ' . checked( $enable_attachment, 'enable', false ) . ' />' . esc_html__( 'Enable', 'full-text-search' ) . '</label></p>';
		echo '<p><label><input name="full_text_search_options[enable_attachment]" type="radio" value="filter" ' . checked( $enable_attachment, 'filter', false ) . ' />' . esc_html__( 'Only valid for the ones selected below', 'full-text-search' ) . '</label></p>';

		echo '<ul>';
		echo '<li><label for="field_enable_zip"><input type="checkbox" name="full_text_search_options[enable_zip]" id="field_enable_zip" value="1" ' . checked( $zip, true, false ) . '> ' . esc_html__( 'ZIP', 'full-text-search' ) . '</label></li>';
		echo '<li><label for="field_enable_pdf"><input type="checkbox" name="full_text_search_options[enable_pdf]" id="field_enable_pdf" value="1" ' . checked( $pdf, true, false ) . '> ' . esc_html__( 'PDF', 'full-text-search' ) . '</label></li>';
		echo '<li><label for="field_enable_word"><input type="checkbox" name="full_text_search_options[enable_word]" id="field_enable_word" value="1" ' . checked( $word, true, false ) . '> ' . esc_html__( 'Word (doc, docx)', 'full-text-search' ) . '</label></li>';
		echo '<li><label for="field_enable_excel"><input type="checkbox" name="full_text_search_options[enable_excel]" id="field_enable_excel" value="1" ' . checked( $excel, true, false ) . '> ' . esc_html__( 'Excel (xlsx)', 'full-text-search' ) . '</label></li>';
		echo '<li><label for="field_enable_powerpoint"><input type="checkbox" name="full_text_search_options[enable_powerpoint]" id="field_enable_powerpoint" value="1" ' . checked( $powerpoint, true, false ) . '> ' . esc_html__( 'PowerPoint (pptx)', 'full-text-search' ) . '</label></li>';
		echo '</ul>';

		echo '</fieldset>';
	}

	/**
	 * Register Enable automatic text extraction.
	 *
	 * @since 2.0.0
	 */
	public function field_enable_auto_text() {
		$pdf        = $this->parent->options['auto_pdf'] ?? true;
		$word       = $this->parent->options['auto_word'] ?? true;
		$excel      = $this->parent->options['auto_excel'] ?? true;
		$powerpoint = $this->parent->options['auto_powerpoint'] ?? true;

		echo '<fieldset id="enable-auto-text"><legend class="screen-reader-text"><span>' . esc_html__( 'Automatic text extraction', 'full-text-search' ) . '</span></legend>';
		echo '<ul>';
		echo '<li><label for="auto_pdf"><input type="checkbox" name="full_text_search_options[auto_pdf]" id="auto_pdf" value="1" ' . checked( $pdf, true, false ) . '> ' . esc_html__( 'PDF', 'full-text-search' ) . '</label></li>';
		echo '<li><label for="auto_word"><input type="checkbox" name="full_text_search_options[auto_word]" id="auto_word" value="1" ' . checked( $word, true, false ) . '> ' . esc_html__( 'Word (doc, docx)', 'full-text-search' ) . '</label></li>';
		echo '<li><label for="auto_excel"><input type="checkbox" name="full_text_search_options[auto_excel]" id="auto_excel" value="1" ' . checked( $excel, true, false ) . '> ' . esc_html__( 'Excel (xlsx)', 'full-text-search' ) . '</label></li>';
		echo '<li><label for="auto_powerpoint"><input type="checkbox" name="full_text_search_options[auto_powerpoint]" id="auto_powerpoint" value="1" ' . checked( $powerpoint, true, false ) . '> ' . esc_html__( 'PowerPoint (pptx)', 'full-text-search' ) . '</label></li>';
		echo '</ul>';
		echo '</fieldset>';
		echo '<p class="description">' . esc_html__( 'Automatic text extraction is only done when adding a file and when regenerating if the search text is empty.', 'full-text-search' ) . '</p>';
	}

	/**
	 * Register search target.
	 *
	 * @since 2.10.0
	 */
	public function field_search_target() {
		$shortcode = $this->parent->options['search_shortcode'] ?? false;
		$block     = $this->parent->options['search_block'] ?? false;
		$html      = $this->parent->options['search_html'] ?? false;

		echo '<fieldset id="search-target"><legend class="screen-reader-text"><span>' . esc_html__( 'Search target', 'full-text-search' ) . '</span></legend>';
		echo '<ul>';
		echo '<li><label for="search_shortcode"><input type="checkbox" name="full_text_search_options[search_shortcode]" id="search_shortcode" value="1" ' . checked( $shortcode, true, false ) . '> ' . esc_html__( 'Shortcode content', 'full-text-search' ) . '</label></li>';
		echo '<li><label for="search_block"><input type="checkbox" name="full_text_search_options[search_block]" id="search_block" value="1" ' . checked( $block, true, false ) . '> ' . esc_html__( 'Synced Pattern content', 'full-text-search' ) . '</label></li>';
		echo '<li><label for="search_html"><input type="checkbox" name="full_text_search_options[search_html]" id="search_html" value="1" ' . checked( $html, true, false ) . '> ' . esc_html__( 'HTML tags', 'full-text-search' ) . '</label></li>';
		echo '</ul>';
		echo '</fieldset>';
		echo '<p class="description">' . esc_html__( 'This option will be reflected when the post is updated. To apply existing posts, you need to perform a regeneration from maintenance.', 'full-text-search' ) . '</p>';
	}

	/**
	 * Sanitize our setting.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input Input data.
	 */
	public function sanitize( $input ) {
		$this->parent->options['enable_mode'] = $input['enable_mode'];
		$this->parent->options['sort_order']  = $input['sort_order'];

		$this->parent->options['display_score']         = ( isset( $input['display_score'] ) && '1' === $input['display_score'] );
		$this->parent->options['highlight']             = ( isset( $input['highlight'] ) && '1' === $input['highlight'] );
		$this->parent->options['markjs']                = ( isset( $input['markjs'] ) && '1' === $input['markjs'] );
		$this->parent->options['search_result_content'] = $input['search_result_content'];

		$this->parent->options['enable_attachment'] = $input['enable_attachment'];
		if ( 'filter' === $this->parent->options['enable_attachment'] ) {
			$this->parent->options['enable_zip']        = ( isset( $input['enable_zip'] ) && '1' === $input['enable_zip'] );
			$this->parent->options['enable_pdf']        = ( isset( $input['enable_pdf'] ) && '1' === $input['enable_pdf'] );
			$this->parent->options['enable_word']       = ( isset( $input['enable_word'] ) && '1' === $input['enable_word'] );
			$this->parent->options['enable_excel']      = ( isset( $input['enable_excel'] ) && '1' === $input['enable_excel'] );
			$this->parent->options['enable_powerpoint'] = ( isset( $input['enable_powerpoint'] ) && '1' === $input['enable_powerpoint'] );

			if (
				! $this->parent->options['enable_zip'] &&
				! $this->parent->options['enable_pdf'] &&
				! $this->parent->options['enable_word'] &&
				! $this->parent->options['enable_excel'] &&
				! $this->parent->options['enable_powerpoint']
			) {
				$this->parent->options['enable_attachment'] = 'disable';
			}
		}

		$this->parent->options['auto_pdf']        = ( isset( $input['auto_pdf'] ) && '1' === $input['auto_pdf'] );
		$this->parent->options['auto_word']       = ( isset( $input['auto_word'] ) && '1' === $input['auto_word'] );
		$this->parent->options['auto_excel']      = ( isset( $input['auto_excel'] ) && '1' === $input['auto_excel'] );
		$this->parent->options['auto_powerpoint'] = ( isset( $input['auto_powerpoint'] ) && '1' === $input['auto_powerpoint'] );

		$this->parent->options['search_shortcode'] = ( isset( $input['search_shortcode'] ) && '1' === $input['search_shortcode'] );
		$this->parent->options['search_block']     = ( isset( $input['search_block'] ) && '1' === $input['search_block'] );
		$this->parent->options['search_html']      = ( isset( $input['search_html'] ) && '1' === $input['search_html'] );

		return $this->parent->options;
	}

	/**
	 * Adds columns to the Media list table in the admin.
	 *
	 * @since 1.7.0
	 *
	 * @param string[] $columns An array of columns displayed in the Media list table.
	 * @return string[] The modified columns.
	 */
	public function manage_media_columns( $columns ) {
		echo '<style>.fixed .column-fulltext { width: 10%; }</style>';
		$columns['fulltext'] = __( 'Search character count', 'full-text-search' );
		return $columns;
	}

	/**
	 * Display additional items in the Media list table.
	 *
	 * @since 1.7.0
	 *
	 * @param string $column_name Name of the custom column.
	 * @param int    $post_id     Attachment ID.
	 * @return void
	 */
	public function manage_media_custom_column( $column_name, $post_id ) {
		if ( 'fulltext' === $column_name ) {
			$row = $this->parent->get_fulltext_row( $post_id );
			if ( $row ) {
				$status = (int) $row->status;
				if ( 0 === $status ) {
					$len = mb_strlen( $row->keywords );
					if ( 0 !== $len ) {
						echo esc_html( number_format( $len ) );
					}
				} elseif ( 1 === $status ) {
					esc_html_e( '(Error)', 'full-text-search' );
				} elseif ( 2 === $status ) {
					esc_html_e( '(PDF Secured)', 'full-text-search' );
				} elseif ( 3 === $status ) {
					esc_html_e( '(Unsupported)', 'full-text-search' );
				} else {
					esc_html_e( '(Error)', 'full-text-search' );
				}
			}
		}
	}

	/**
	 * Filters the attachment fields to edit.
	 *
	 * @since 1.8.0
	 *
	 * @param array   $form_fields An array of attachment form fields.
	 * @param WP_Post $post        The WP_Post attachment object.
	 * @return array Filtered attachment form fields.
	 */
	public function attachment_fields_to_edit( $form_fields, $post ) {
		$search_text = get_post_meta( $post->ID, Full_Text_Search::CUSTOM_FIELD_NAME, true );

		if ( empty( $search_text ) ) {
			$row = $this->parent->get_fulltext_row( $post->ID );
			if ( $row ) {
				if ( 0 === (int) $row->status ) {
					$search_text = $row->keywords;
				}
			}
		}

		$form_fields['search_text'] = array(
			'label' => __( 'Search text', 'full-text-search' ),
			'input' => 'textarea',
			'value' => esc_textarea( $search_text ),
			'helps' => __( 'This is the target text for full-text search. If you have enabled automatic text extraction, it will be stored here.', 'full-text-search' ),
		);

		return $form_fields;
	}

	/**
	 * Filters the attachment fields to be saved.
	 *
	 * @since 1.8.0
	 *
	 * @param array $post       An array of post data.
	 * @param array $attachment An array of attachment metadata.
	 * @return array Filtered attachment post object.
	 */
	public function attachment_fields_to_save( $post, $attachment ) {
		$search_text = '';
		if ( isset( $attachment['search_text'] ) ) {
			$search_text = $attachment['search_text'];
			update_post_meta( $post['ID'], Full_Text_Search::CUSTOM_FIELD_NAME, $search_text );
		} else {
			delete_post_meta( $post['ID'], Full_Text_Search::CUSTOM_FIELD_NAME );
		}

		// The index is updated with update_post hook.

		return $post;
	}

	/**
	 * Filters the action links displayed for each plugin in the Plugins list table.
	 *
	 * @since 2.2.0
	 *
	 * @param string[] $actions     An array of plugin action links.
	 * @param string   $plugin_file Path to the plugin file relative to the plugins directory.
	 * @return array Filtered an array of plugin action links.
	 */
	public function plugin_action_links( $actions, $plugin_file ) {
		if ( 'full-text-search.php' === basename( $plugin_file ) ) {
			$settings = array(
				'settings' => '<a href="' . admin_url( 'options-general.php?page=full-text-search' ) . '">' . __( 'Settings', 'full-text-search' ) . '</a>',
			);
			$actions  = array_merge( $settings, $actions );
		}
		return $actions;
	}
}
