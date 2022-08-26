<?php

/**
 * Full-Text Search class.
 *
 * @since 1.0.0
 */
class Full_Text_Search {
	const TABLE_NAME        = 'full_text_search_posts';
	const CUSTOM_FIELD_NAME = 'full_text_search_search_text';

	public $options;
	public $admin;
	public $pdfparser;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		load_plugin_textdomain( 'full-text-search' );

		register_activation_hook( __FILE__, array( $this, 'activation' ) );

		$this->options = get_option( 'full_text_search_options' );
		if ( false === $this->options || version_compare( $this->options['plugin_version'], FULL_TEXT_SEARCH_VERSION, '<' ) ) {
			$this->activation();
			$this->options = get_option( 'full_text_search_options' );
		}

		if ( false === $this->options ) {
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			return;
		}

		require_once __DIR__ . '/admin.php';
		$this->admin = new Full_Text_Search_Admin( $this );

		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	/**
	 * Fires once activated plugins have loaded.
	 *
	 * @since 1.0.0
	 */
	public function plugins_loaded() {
		add_action( 'full_text_search_event', array( $this, 'update_index_data' ) );
		add_action( 'wp_insert_post', array( $this, 'update_post' ), 10, 3 );
		add_action( 'add_attachment', array( $this, 'add_attachment' ), 10 );
		add_action( 'attachment_updated', array( $this, 'update_attachment' ), 10, 3 );
		add_action( 'deleted_post', array( $this, 'delete_post' ), 100, 1 );
		add_filter( 'posts_search', array( $this, 'posts_search' ), 10, 2);
		add_filter( 'posts_clauses_request', array( $this, 'posts_clauses_request' ), 99999, 2 );

		if ( isset( $this->options['enable_attachment'] ) && 'disable' !== $this->options['enable_attachment'] ) {
			add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
			add_filter( 'posts_where', array( $this, 'posts_where' ), 10, 2 );
		}

		if ( ! isset( $this->options['auto_pdf'] ) || $this->options['auto_pdf'] ) {
			if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
				require_once __DIR__ . '/vendor/autoload.php';
			}
			$this->pdfparser = null;
		}

		if ( isset( $this->options['display_score'] ) && $this->options['display_score'] ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_filter( 'get_the_excerpt', array( $this, 'get_the_excerpt' ), 10, 2 );
		}
	}

	/**
	 * Prints admin screen notices.
	 *
	 * @since 1.0.0
	 */
	public function admin_notices() {
		global $pagenow;

		if ( 'plugins.php' === $pagenow || 'index.php' === $pagenow ) {
			printf( '<div class="notice notice-warning"><p><b>%s</b>: %s</p></div>',
				esc_html( __( 'Full-Text Search', 'full-text-search' ) ),
				esc_html( __( 'Requires MySQL 5.6 or higher, or Mroonga engine.', 'full-text-search' ) )
			);
		}
	}

	/**
	 * Filters the search SQL that is used in the WHERE clause of WP_Query.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $search Search SQL for WHERE clause.
	 * @param WP_Query $query  The WP_Query instance (passed by reference).
	 * @return string Search SQL for WHERE clause.
	 */
	public function posts_search( $search, $query ) {
		if ( $search ) {
			$enable_mode = isset( $this->options['enable_mode'] ) ? $this->options['enable_mode'] : 'enable';
			if (
				( 'enable' === $enable_mode ) ||
				( 'search' === $enable_mode && $query->is_main_query() && $query->is_search && ! $query->is_admin )
			) {
				$search = '';
			}
		}
		return $search;
	}

	/**
	 * Add attachments to the search query.
	 *
	 * @since 1.6.0
	 *
	 * @param WP_Query $query Query instance.
	 * @return void.
	 */
	public function pre_get_posts( $query ) {
		if ( $query->is_search && ! $query->is_admin ) {
			$query->set( 'post_status', array( 'publish', 'private', 'inherit' ) );
		}
	}

	/**
	 * Normalize the search string for Mroonga.
	 *
	 * @since 2.5.0
	 *
	 * @param string $input Search string.
	 * @return string Normalized search string.
	 */
	private function normalize_search_string_for_mroonga( $input ) {
		$ws = preg_split( '//u', $input . ' ', -1, PREG_SPLIT_NO_EMPTY );
		$in = false;
		$w = '';
		$a = array();
		foreach ( $ws as $s ) {
			if ( '　' === $s && ! $in ) {
				$s = ' ';
			}
			if ( ' ' === $s && ! $in ) {
				if ( '' !== $w && ' ' !== $w ) {
					$a[] = $w;
				}
				$w = '';
			} else {
				$w .= $s;
				if ( '"' === $s ) {
					$in = ! $in;
				}
			}
		}
		return implode( ' ', $a );
	}

	/**
	 * Normalize the search string for InnoDB.
	 *
	 * @since 2.5.0
	 *
	 * @param string $input Search string.
	 * @return string Normalized search string.
	 */
	private function normalize_search_string_for_innodb( $input ) {
		$ws = preg_split( '//u', $input . ' ', -1, PREG_SPLIT_NO_EMPTY );
		$in = false;
		$or = false;
		$w = '';
		$a = array();
		foreach ( $ws as $s ) {
			if ( '　' === $s && ! $in ) {
				$s = ' ';
			}
			if ( ' ' === $s && ! $in ) {
				if ( 'OR' === $w ) {
					$w = '';
					$or = true;
				}
				if ( 'AND' === $w ) {
					$w = '';
					$or = false;
				}
				if ( '' !== $w && ' ' !== $w  ) {
					if ( ! in_array( substr( $w, 0, 1 ), array( '+', '-', '~', '@' ) ) ) {
						if ( $or ) {
							$count = count( $a );
							if ( $count > 0 ) {
								$a[$count - 1] = ltrim( $a[$count - 1], '+' );
							}
							$or = false;
						} else {
							$w = '+' . $w;
						}
					}
					$a[] = $w;
				}
				$w = '';
			} else if ( ( '(' === $s  || ')' === $s ) && ! $in ) {
				if ( $or ) {
					$count = count( $a );
					if ( $count > 0 ) {
						$a[$count - 1] = ltrim( $a[$count - 1], '+' );
					}
					$or = false;
				}
				$a[] = $w;
				$a[] = $s;
				$w = '';
			} else {
				$w .= $s;
				if ( '"' === $s ) {
					$in = ! $in;
				}
			}
		}
		return implode( ' ', $a );
	}

	/**
	 * Filters all query clauses at once, for convenience.
	 *
	 * @since 2.8.0
	 *
	 * @param string[] $clauses Associative array of the clauses for the query.
	 * @param WP_Query $query   The WP_Query instance (passed by reference).
	 * @return string[].
	 */
	public function posts_clauses_request( $clauses, $query ) {
		if ( $query->is_search ) {
			$enable_mode = isset( $this->options['enable_mode'] ) ? $this->options['enable_mode'] : 'enable';
			$s = trim( sanitize_text_field( $query->get( 's', '' ) ) );

			if ( ( 'enable' === $enable_mode || ( 'search' === $enable_mode && is_search() ) ) && ! empty( $s ) ) {
				global $wpdb;

				$table_name = $wpdb->prefix . Full_Text_Search::TABLE_NAME;

				$q = $query->fill_query_vars( $query->query_vars );
				$post_type = ( ! isset( $q['post_type'] ) ) ? 'any' : $q['post_type'];

				if ( 'any' === $post_type ) {
					$in_search_post_types = get_post_types( array( 'exclude_from_search' => false ) );
					if ( empty( $in_search_post_types ) ) {
						$post_type_where = ' AND 1=0 ';
					} else {
						$post_type_where = " AND post_type IN ('" . implode( "', '", array_map( 'esc_sql', $in_search_post_types ) ) . "')";
					}
				} elseif ( ! empty( $post_type ) && is_array( $post_type ) ) {
					$post_type_where = " AND post_type IN ('" . implode( "', '", esc_sql( $post_type ) ) . "')";
				} elseif ( ! empty( $post_type ) ) {
					$post_type_where = $wpdb->prepare( " AND post_type = %s", $post_type );
				} elseif ( $query->is_attachment ) {
					$post_type_where = " AND post_type = 'attachment'";
				} elseif ( $query->is_page ) {
					$post_type_where = " AND post_type = 'page'";
				} else {
					$post_type_where = " AND post_type = 'post'";
				}

				$join = isset( $clauses['join'] ) ? $clauses['join'] : '';
				$fields = isset( $clauses['fields'] ) ? $clauses['fields'] : '';
				$orderby = isset( $clauses['orderby'] ) ? $clauses['orderby'] : '';

				if ( 'mroonga' === $this->options['db_engine'] ) {
					$s = "*D+ " . $this->normalize_search_string_for_mroonga( $s );
				} else {
					$s = $this->normalize_search_string_for_innodb( $s );
				}

				$join .= $wpdb->prepare(
					" INNER JOIN (" .
					"SELECT ID, post_type, MATCH(keywords, post_title, post_content, post_excerpt) AGAINST(%s IN BOOLEAN MODE) AS score " .
					"FROM {$table_name} " .
					"WHERE MATCH(keywords, post_title, post_content, post_excerpt) AGAINST(%s IN BOOLEAN MODE)" .
					$post_type_where .
					") matched_posts ON matched_posts.ID = {$wpdb->posts}.ID",
					$s, $s
				);

				$fields .= ',score AS search_score';

				if ( ! isset( $this->options['sort_order'] ) || 'score' === $this->options['sort_order'] ) {
					$orderby = 'score DESC';
				}

				$clauses['join'] = $join;
				$clauses['fields'] = $fields;
				$clauses['orderby'] = $orderby;
			}
		}

		return $clauses;
	}

	/**
	 * Filters the WHERE clause of the query.
	 *
	 * @since 1.6.0
	 *
	 * @param string   $where The WHERE clause of the query.
	 * @param WP_Query $query The WP_Query instance (passed by reference).
	 * @return string WHERE clause.
	 */
	public function posts_where( $where, $query ) {
		if ( $query->is_search && ! $query->is_admin ) {
			global $wpdb;

			if ( isset( $this->options['enable_attachment'] ) && 'filter' === $this->options['enable_attachment'] ) {
				$post_mime_types = array();
				if ( isset( $this->options['enable_zip'] ) && $this->options['enable_zip'] ) {
					$post_mime_types[] = "{$wpdb->posts}.post_mime_type = 'application/zip'";
				}
				if ( isset( $this->options['enable_pdf'] ) && $this->options['enable_pdf'] ) {
					$post_mime_types[] = "{$wpdb->posts}.post_mime_type = 'application/pdf'";
				}
				if ( isset( $this->options['enable_word'] ) && $this->options['enable_word'] ) {
					$post_mime_types[] = "{$wpdb->posts}.post_mime_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'";
					$post_mime_types[] = "{$wpdb->posts}.post_mime_type = 'application/msword'";
				}
				if ( isset( $this->options['enable_excel'] ) && $this->options['enable_excel'] ) {
					$post_mime_types[] = "{$wpdb->posts}.post_mime_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'";
				}
				if ( isset( $this->options['enable_powerpoint'] ) && $this->options['enable_powerpoint'] ) {
					$post_mime_types[] = "{$wpdb->posts}.post_mime_type = 'application/vnd.openxmlformats-officedocument.presentationml.presentation'";
				}

				if ( count( $post_mime_types ) ) {
					$post_mime_type = implode( ' OR ', $post_mime_types );
					$where .= " AND ({$wpdb->posts}.post_type <> 'attachment' OR {$wpdb->posts}.post_type = 'attachment' AND ({$post_mime_type}))";
				}
			}
		}
		return $where;
	}

	/**
	 * Update a index data.
	 *
	 * @since 2.6.0
	 *
	 * @param int     $post_ID  Post ID.
	 * @param WP_Post $post     Post object.
	 * @param string  $keywords Keywords (Search text).
	 * @param bool    $update   Whether this is an existing post being updated.
	 * @return void
	 */
	private function update_index_post( $post_ID, $post, $keywords, $updated = false ) {
		global $wpdb;

		$table_name = $wpdb->prefix . Full_Text_Search::TABLE_NAME;

		if ( null === $keywords ) {
			$keywords = get_post_meta( $post_ID, Full_Text_Search::CUSTOM_FIELD_NAME, true );
		}

		$status = 0;

		if ( 'attachment' == $post->post_type && empty( $keywords ) && ! $updated ) {
			if  ( 'application/pdf' == $post->post_mime_type ) {
				if ( isset( $this->options['auto_pdf'] ) ? $this->options['auto_pdf'] : true ) {
					$status = 1;
				}
			} else if ( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' == $post->post_mime_type || 'application/msword' == $post->post_mime_type ) {
				if ( isset( $this->options['auto_word'] ) ? $this->options['auto_word'] : true ) {
					$status = 1;
				}
			} else if ( 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' == $post->post_mime_type ) {
				if ( isset( $this->options['auto_excel'] ) ? $this->options['auto_excel'] : true ) {
					$status = 1;
				}
			} else if ( 'application/vnd.openxmlformats-officedocument.presentationml.presentation' == $post->post_mime_type ) {
				if ( isset( $this->options['auto_powerpoint'] ) ? $this->options['auto_powerpoint'] : true ) {
					$status = 1;
				}
			}
		}

		$data = array(
			'ID'            => $post->ID,
			'post_type'     => $post->post_type,
			'post_status'   => $post->post_status,
			'post_password' => $post->post_password,
			'post_modified' => $post->post_modified,
			'post_title'    => wp_strip_all_tags( $post->post_title ),
			'post_content'  => wp_strip_all_tags( $post->post_content ),
			'post_excerpt'  => wp_strip_all_tags( $post->post_excerpt ),
			'keywords'      => $keywords,
			'status'        => $status
		);

		/**
		 * Filter a index data.
		 *
		 * @since 2.6.0
		 *
		 * @param array $data    See wpdb::replace()
		 * @param int   $post_ID Post ID.
		 */
		$data = apply_filters( 'full_text_search_index_post', $data, $post_ID );

		$wpdb->replace( $table_name, $data );

		$status = isset( $data['status'] ) ? $data['status'] : 4;
		if ( 1 === $status ) {
			$filename = get_attached_file( $post_ID );
			if ( file_exists( $filename ) ) {
				if ( 'application/pdf' === $post->post_mime_type ) {
					try {
						$keywords = $this->get_text_from_pdf_file( $filename );
						$status = 0;
					} catch ( \Exception $e ) {
						$status = 2;
					}
				} else if ( 'application/msword' === $post->post_mime_type ) {
					try {
						$keywords = $this->get_text_from_doc_file( $filename );
						$status = 0;
					} catch ( Exception $e ) {
						$error = $e->getMessage();
						if ( 'Unsupported file format.' === $error ) {
							$status = 3;
						} else {
							$status = 4;
						}
					}
				} else if ( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' === $post->post_mime_type ) {
					try {
						$keywords = $this->get_text_from_docx_file( $filename );
						$status = 0;
					} catch ( Exception $e ) {
						$status = 4;
					}
				} else if ( 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' === $post->post_mime_type ) {
					try {
						$keywords = $this->get_text_from_excel_file( $filename );
						$status = 0;
					} catch ( Exception $e ) {
						$status = 4;
					}
				} else if ( 'application/vnd.openxmlformats-officedocument.presentationml.presentation' === $post->post_mime_type ) {
					try {
						$keywords = $this->get_text_from_powerpoint_file( $filename );
						$status = 0;
					} catch ( Exception $e ) {
						$status = 4;
					}
				}

				if ( ! empty( $keywords ) ) {
					update_post_meta( $post_ID, Full_Text_Search::CUSTOM_FIELD_NAME, $keywords );
				}

				$wpdb->query( $wpdb->prepare( "UPDATE {$table_name} SET keywords=%s,status=%d WHERE ID=%d;",
					$keywords,
					$status,
					$post->ID
				) );
			}
		}
	}

	/**
	 * Update a post.
	 *
	 * @since 1.2.0
	 *
	 * @param int     $post_ID Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 * @return void
	 */
	public function update_post( $post_ID, $post, $update ) {
		if ( ! in_array( $post->post_type, array( 'revision', 'custom_css', 'customize_changeset' ) ) ) {
			$this->update_index_post( $post_ID, $post, null, $update );
		}
	}

	/**
	 * Add attachment.
	 *
	 * @since 1.4.0
	 *
	 * @param int $post_ID Post ID.
	 * @return void
	 */
	public function add_attachment( $post_ID ) {
		$post = get_post( $post_ID );
		$this->update_post( $post_ID, $post, false );
	}

	/**
	 * Update attachment.
	 *
	 * @since 1.8.0
	 *
	 * @param int $post_ID Post ID.
	 * @return void
	 */
	public function update_attachment( $post_ID, $post_after, $post_before ) {
		$this->update_post( $post_ID, $post_after, true );
	}

	/**
	 * Delete a post.
	 *
	 * @since 1.2.0
	 *
	 * @param int $postid Post ID.
	 * @return void
	 */
	public function delete_post( $post_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . Full_Text_Search::TABLE_NAME;
		$wpdb->delete( $table_name, array( 'ID' => $post_id ) );
	}

	/**
	 * Gets the default value of the options.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_default_options() {
		return array( 
			'plugin_version'    => FULL_TEXT_SEARCH_VERSION,
			'db_type'           => '',          // '', 'mariadb' or 'mysql',
			'db_engine'         => '',          // '', 'mroonga' or'innodb',
			'sort_order'        => 'score',     // 'default' or 'score'.
			'enable_mode'       => 'enable',    // 'disable', 'enable' or 'search'.
			'enable_attachment' => 'enable',    // 'disable', 'enable' or 'filter'.
			'enable_zip'        => false,
			'enable_pdf'        => false,
			'enable_word'       => false,
			'enable_excel'      => false,
			'enable_powerpoint' => false,
			'auto_pdf'          => true,
			'auto_word'         => true,
			'auto_excel'        => true,
			'auto_powerpoint'   => true,
			'display_score'     => true,
		);
	}

	/**
	 * Create a search table.
	 *
	 * @since 1.0.0
	 *
	 * @param string $db_engine DB engine. Accepts 'mroonga' or 'innodb'.
	 * @return bool
	 */
	public function create_table( $db_engine ) {
		global $wpdb;

		if ( 'mroonga' !== $db_engine && 'innodb' !== $db_engine ) {
			return false;
		}

		$result = false;

		$db_index = ( 'mroonga' == $db_engine ) ?
			'FULLTEXT INDEX (keywords,post_title,post_content,post_excerpt) COMMENT \'parser "TokenMecab"\'' :
			'FULLTEXT INDEX (keywords,post_title,post_content,post_excerpt) WITH PARSER ngram';

		//$charset_collate = $wpdb->get_charset_collate();
		$charset_collate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';

		$table_name = $wpdb->prefix . Full_Text_Search::TABLE_NAME;

		$table_engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME=%s', $table_name ) );
		if ( null === $table_engine ) {
			$result = $wpdb->query(
				"CREATE TABLE `{$table_name}` (
				ID bigint(20) unsigned NOT NULL auto_increment,
				post_type varchar(20) NOT NULL default 'post',
				post_status varchar(20) NOT NULL default 'publish',
				post_password varchar(255) NOT NULL default '',
				post_modified datetime NOT NULL default CURRENT_TIMESTAMP,
				post_title text NOT NULL,
				post_content longtext NOT NULL,
				post_excerpt text NOT NULL,
				keywords longtext NOT NULL,
				status tinyint(1) NOT NULL default '0',
				PRIMARY KEY (ID),
				{$db_index},
				KEY post_type (post_type)
				) ENGINE={$db_engine} {$charset_collate};"
			);
		} else if ( $db_engine != strtolower( $table_engine ) ) {
			$engine = ( 'mroonga' == $db_engine ) ? 'Mroonga' : 'InnoDB';
			$result = $wpdb->query( "ALTER TABLE {$table_name} ENGINE={$engine}" );
		} else {
			$result = true;
		}

		return $result;
	}

	/**
	 * Extracts text string from a PDF file.
	 *
	 * @since 2.0.0
	 *
	 * @throws Exception The file fails to load.
	 *
	 * @param string $file    Path to the file.
	 * @param string $newline Newline characters. Default is \n.
	 * @return string text string.
	 */
	public function get_text_from_pdf_file( $file, $newline = "\n" ) {
		if ( ! isset( $this->pdfparser ) ) {
			$config = new Smalot\PdfParser\Config();
			$config->setRetainImageContent( false );
			$this->pdfparser = new \Smalot\PdfParser\Parser( [], $config );
		}
		$pdffile = $this->pdfparser->parseFile( $file );
		$text = $pdffile->getText();

		$text = str_replace( array( "\r\n", "\r", "\n", "\t", '　' ), '', $text );

		return trim( $text );
	}

	/**
	 * Extracts text string from a Word (docx) file.
	 *
	 * @since 2.0.0
	 *
	 * @throws Exception The file fails to load.
	 *
	 * @param string $file    Path to the file.
	 * @param string $newline Newline characters. Default is \n.
	 * @return string text string.
	 */
	public function get_text_from_docx_file( $file, $newline = "\n" ) {
		$text = '';

		$zip = new ZipArchive();
		if ( $zip->open( $file ) === true ) {
			$xml = $zip->getFromName( 'word/document.xml' );
			if ( $xml ) {
				$dom = new DOMDocument();
				$dom->loadXML( $xml );
				$paragraphs = $dom->getElementsByTagName( 'p' );
				foreach ( $paragraphs as $p ) {
					$ts = $p->getElementsByTagName( 't' );
					foreach ( $ts as $t ) {
						$text .= $t->nodeValue;
					}
					$text .= $newline;
				}
				$text = trim( $text );
			}
			$zip->close();
		} else {
			throw new Exception( 'File open error.' );
		}

		return $text;
	}

	/**
	 * Extracts text string from a Word (doc) file.
	 *
	 * @since 2.0.0
	 *
	 * @throws Exception The file fails to load.
	 *
	 * @param string $file    Path to the file.
	 * @return string text string.
	 */
	public function get_text_from_doc_file( $file ) {
		$text = '';

		if ( ( $fh = fopen( $file, 'r' ) ) !== false ) {
			$headers = fread( $fh, 0xA00 );
			$len = ( ord( $headers[0x21C] ) - 1 );
			$len += ( ( ord( $headers[0x21D] ) - 8 ) * 256 );
			$len += ( ( ord( $headers[0x21E] ) * 256 ) * 256 );
			$len += ( ( ( ord( $headers[0x21F] ) * 256 ) * 256 ) * 256 );
			if ( $len > 0 ) {
				$text = fread( $fh, $len );
				$text = mb_convert_encoding( $text, 'UTF-8', 'UTF-16LE' );
				$text = preg_replace( '/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $text );
				$text = trim( $text );
			}
			fclose( $fh );
			if ( $len <= 0 ) {
				throw new Exception( 'Unsupported file format.' );
			}
		} else {
			throw new Exception( 'File open error.' );
		}

		return $text;
	}

	/**
	 * Extracts text string from a Excel (xlsx) file.
	 *
	 * @since 2.1.0
	 *
	 * @throws Exception The file fails to load.
	 *
	 * @param string $file    Path to the file.
	 * @param string $newline Newline characters. Default is \n.
	 * @return string text string.
	 */
	public function get_text_from_excel_file( $file, $newline = "\n" ) {
		$text = '';

		$zip = new ZipArchive();
		if ( $zip->open( $file ) === true ) {
			$xml = $zip->getFromName( 'xl/sharedStrings.xml' );
			if ( $xml ) {
				$dom = new DOMDocument();
				$dom->loadXML( $xml );
				$ts = $dom->getElementsByTagName( 't' );
				foreach ( $ts as $t ) {
					$text .= $t->nodeValue;
					$text .= $newline;
				}
				$text = trim( $text );
			}
			$zip->close();
		} else {
			throw new Exception( 'File open error.' );
		}

		return $text;
	}

	/**
	 * Extracts text string from a PowerPoint (pptx) file.
	 *
	 * @since 2.1.0
	 *
	 * @throws Exception The file fails to load.
	 *
	 * @param string $file    Path to the file.
	 * @param string $newline Newline characters. Default is \n.
	 * @return string text string.
	 */
	public function get_text_from_powerpoint_file( $file, $newline = "\n" ) {
		$text = '';

		$zip = new ZipArchive();
		if ( $zip->open( $file ) === true ) {
			$dom = new DOMDocument();

			$i = 1;
			while ( false !== ( $xml = $zip->getFromName( "ppt/slides/slide{$i}.xml" ) ) ) {
				$dom->loadXML( $xml );
				$paragraphs = $dom->getElementsByTagName( 'p' );
				foreach ( $paragraphs as $p ) {
					$ts = $p->getElementsByTagName( 't' );
					foreach ( $ts as $t ) {
						$text .= $t->nodeValue;
					}
					$text .= $newline;
				}
				$i++;
			}

			$i = 1;
			while ( false !== ( $xml = $zip->getFromName( "ppt/diagrams/data{$i}.xml" ) ) ) {
				$dom->loadXML( $xml );
				$paragraphs = $dom->getElementsByTagName( 'p' );
				foreach ( $paragraphs as $p ) {
					$ts = $p->getElementsByTagName( 't' );
					foreach ( $ts as $t ) {
						$text .= $t->nodeValue;
					}
					$text .= $newline;
				}
				$i++;
			}

			$text = trim( $text );
			$zip->close();
		} else {
			throw new Exception( 'File open error.' );
		}

		return $text;
	}

	/**
	 * Update index data.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function update_index_data() {
		global $wpdb;

		$table_name = $wpdb->prefix . Full_Text_Search::TABLE_NAME;

		$posts_count = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type NOT IN ('revision','custom_css','customize_changeset');" );

		/**
		 * Maximum number of records to process at one time.
		 *
		 * @since 1.1.0
		 * @since 2.2.0 Added the `$posts_count` parameter.
		 *
		 * @param int $limit       Number of records. 0 to stop indexing.
		 * @param int $posts_count Number of search targets.
		 */
		$limit = (int) apply_filters( 'full_text_search_limit', 2000 >= $posts_count ? 100 : 1000, $posts_count );

		if ( 0 === $limit ) {
			return;
		}

		/*
		 * Delete
		 */
		$wpdb->query( "DELETE FROM {$table_name} WHERE ID NOT IN (SELECT ID FROM {$wpdb->posts} WHERE post_type NOT IN ('revision','custom_css','customize_changeset'))" );

		/*
		 * Update
		 */
		$rows = $wpdb->get_results(
			"SELECT SQL_CALC_FOUND_ROWS t1.ID, t1.post_type, t1.post_mime_type, t1.post_status, t1.post_password, t1.post_modified, t1.post_title, t1.post_content, t1.post_excerpt, m.meta_value AS keywords " .
			"FROM {$wpdb->posts} AS t1 LEFT OUTER JOIN {$table_name} AS t2 ON (t1.ID = t2.ID) " .
			"LEFT JOIN {$wpdb->postmeta} AS m ON t1.ID = m.post_id AND m.meta_key = '" . Full_Text_Search::CUSTOM_FIELD_NAME . "' " . 
			"WHERE (t2.ID IS NULL OR t1.post_modified > t2.post_modified) AND t1.post_type NOT IN ('revision','custom_css','customize_changeset') LIMIT {$limit};"
		);

		$found_rows = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );

		foreach ( $rows as $row ) {
			$this->update_index_post( $row->ID, $row, $row->keywords );
		}

		if ( 0 < $found_rows ) {
			if ( ! wp_get_schedule( 'full_text_search_event' ) ) {
				wp_schedule_single_event( time() + 1, 'full_text_search_event' );
			}
		}
	}

	/**
	 * Get the search data by specifying the post ID.
	 *
	 * @since 1.8.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int $post_id Post ID
	 * @return object|null Database query result. null on failure.
	 */
	public function get_fulltext_row( $post_id ) {
		$post_id = (int) $post_id;
		if ( ! $post_id ) {
			return null;
		}

		$_post = wp_cache_get( $post_id, 'fulltext_search_index_post' );

		if ( ! $_post ) {
			global $wpdb;

			$table_name = $wpdb->prefix . Full_Text_Search::TABLE_NAME;
			$_post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE ID = %d LIMIT 1", $post_id ) );

			if ( ! $_post ) {
				return null;
			}

			wp_cache_add( $_post->ID, $_post, 'fulltext_search_index_post' );
		}

		return $_post;
	}

	/**
	 * Enqueues scripts for this search page.
	 *
	 * @since 2.4.0
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( is_search() ) {
			wp_enqueue_style( 'full-text-search', plugins_url( '/full-text-search.css', __FILE__ ) );
		}
	}

	/**
	 * Filters the post excerpt.
	 *
	 * @since 2.4.0
	 *
	 * @param string  $post_excerpt The post excerpt.
	 * @param WP_Post $post         Post object.
	 * @return string Post excerpt.
	 */
	public function get_the_excerpt( $excerpt, $post ) {
		if ( is_search() && is_main_query() ) {
			$excerpt = 
				'<div class="full-text-search-result-items"><span class="full-text-search-score">' . 
				sprintf( __( 'Search score: %01.2f', 'full-text-search' ), $post->search_score ) . 
				'</span></div>' . $excerpt;
		}
		return $excerpt;
	}

	/**
	 * Plugin activation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function activation() {
		global $wp_version, $wpdb;

		if ( $wpdb->use_mysqli ) {
			$mysql_server_type = mysqli_get_server_info( $wpdb->dbh );
		} else {
			// @codingStandardsIgnoreStart
			$mysql_server_type = mysql_get_server_info( $wpdb->dbh );
			// @codingStandardsIgnoreEnd
		}

		$db_type = stristr( $mysql_server_type, 'mariadb' ) ? 'mariadb' : 'mysql';
		$db_version = $wpdb->get_var( 'SELECT VERSION()' );

		$enable_mroonga = $wpdb->get_var( "SELECT COUNT(*) FROM INFORMATION_SCHEMA.PLUGINS WHERE PLUGIN_NAME='Mroonga'" );
		if ( $enable_mroonga ) {
			$engine = 'mroonga';
		} else {
			if ( 'mysql' === $db_type && version_compare( '5.6', $db_version, '<=' ) ) {
				$engine = 'innodb';
			}
		}
		if ( ! isset( $engine ) ) {
			return;
		}

		$options = get_option( 'full_text_search_options' );
		if ( false === $options ) {
			$options = $this->get_default_options();
			$options['db_type'] = $db_type;
			$options['db_engine'] = $engine;
			add_option( 'full_text_search_options', $options );
		} else {
			// Less than 2.8.0
			if ( version_compare( $options['plugin_version'], '2.8.0', '<' ) ) {
				$table_name = $wpdb->prefix . Full_Text_Search::TABLE_NAME;
				$wpdb->query( "DROP TABLE IF EXISTS {$table_name};" );
			}

			$options['plugin_version'] = FULL_TEXT_SEARCH_VERSION;
			$options['db_type'] = $db_type;
			$options['db_engine'] = $engine;
			update_option( 'full_text_search_options', $options );
		}

		$this->create_table( $engine );

		if ( ! wp_get_schedule( 'full_text_search_event' ) ) {
			wp_schedule_single_event( time() + 10, 'full_text_search_event' );
		}
	}

	/**
	 * Plugin Uninstall site.
	 *
	 * @since 1.8.0
	 *
	 * @return void
	 */
	public static function uninstall_site() {
		global $wpdb;

		if ( wp_get_schedule( 'full_text_search_event' ) ) {
			wp_clear_scheduled_hook( 'full_text_search_event' );
		}

		$table_name = $wpdb->prefix . Full_Text_Search::TABLE_NAME;
		if ( null !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) ) ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table_name};" );
		}

		// delete_metadata( 'post', 0, Full_Text_Search::CUSTOM_FIELD_NAME, false, true );

		delete_option( 'full_text_search_options' );
	}

	/**
	 * Plugin Uninstall all site.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function uninstall() {
		global $wpdb;

		if ( ! is_multisite() ) {
			Full_Text_Search::uninstall_site();
		} else {
			$current_blog_id = get_current_blog_id();
			$blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				Full_Text_Search::uninstall_site();
			}
			switch_to_blog( $current_blog_id );
		}
	}
}
