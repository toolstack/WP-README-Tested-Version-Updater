<?php
# class.update.php
#
# The main update class used in the update script.
#

class update {
	private $platform_null;
	private $platform;
	private $tags;
	private $svn_username;
	private $placeholders;
	private $ini_settings;
	private $config_settings;
	private $plugin_slug;
	private $sys_temp_dir = false;
	private $home_dir = false;
	private $temp_dir = false;
	private $svn_modified;
	private $latest_wp_version = '4.6';
	private $readme;
	private $readme_path;
	private $readme_eol;
	private $stable_tag;

	public function __construct() {
		$this->home_dir = getcwd();

		// We need to set some platform specific settings.
		if( strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' ) {
			$this->platform_null = ' > nul 2>&1';
			$this->platform = 'win';
		} else {
			$this->platform_null = ' > /dev/null 2> 1';
			$this->platform = 'nix';
		}
		
		// For debugging only.
		// $this->platform_null = '';
	}

	/*
	 *
	 * Public functions
	 *
	 */
	public function process_args() {
		GLOBAL $argc, $argv;

		// If we have some slugs on the command line, add them to the list to be processed.
		if( $argc > 1 ) {
			// Get the list of slugs to update.
			for( $i = 1; $i < $argc; $i++ ) {
				$this->slugs[] = trim( $argv[$i] );
			}
		}
	}

	public function get_config() {
		/* Check to see if we have a settings file, the order is:
		 *     - Current directory
		 *     - One directory above current directory
		 */
		$ini_settings = parse_ini_file( './update.ini' );

		$local_ini_settings = array();
		if( file_exists( '../release.ini' ) ) {
			echo 'Local release.ini to use: ../update.ini' . PHP_EOL;

			$local_ini_settings = parse_ini_file( '../update.ini' );
		}

		// Merge the two settings arrays in to a single one.  We can't use array_merge() as
		// we don't want a blank entry to override a setting in another file that has a value.
		// For example svn-username may not be set in the default or plugin ini files but in
		// the local file, but the "key" exists in both.  The "blank" key in the plugin
		// file would wipe out the value in the local file.
		foreach( $local_ini_settings as $key => $value ) {
			if( trim( $value ) != '' ) {
				$ini_settings[$key] = $value;
			}
		}

		// Get the plugin slugs we're going to update.
		$slugs = explode( ',', $ini_settings['plugin-slugs'] );
		
		foreach( $slugs as $slug ) {
			$slug = trim( $slug );
			
			if( $slug != '' ) {
				$this->slugs[] = trim( $slug );
			}
		}
		
		// Retrieve the current WP version from the wordpress.org API.
		$this->set_current_wp_version();

		$this->ini_settings = $ini_settings;
		$this->config_settings = array();

		if( ! empty( $this->ini_settings['temp-dir'] ) && is_dir( $this->ini_settings['temp-dir'] ) ) {
			$this->sys_temp_dir = $this->ini_settings['temp-dir'];
		} else {
			$this->sys_temp_dir = sys_get_temp_dir();
		}
	}

	public function set_config_settings( $slug ) {
		// Now that we have our config variables we can define the placeholders.
		$this->placeholders = array( 'plugin-slug' => $slug, 'wp-version' => $this->latest_wp_version );

		// Now create our configuration settings by taking the ini settings and replacing any placeholders they may contain.
		$this->config_settings = array();
		foreach( $this->ini_settings as $setting => $value ) {
			$this->config_settings[$setting] = $this->release_replace_placeholders( $value, $this->placeholders );
		}
	}
	
	public function set_temp_dir() {
		// Get a temporary working directory to checkout the SVN repo to.
		$this->temp_dir = tempnam( $this->sys_temp_dir, "RTV" );
		unlink( $this->temp_dir );
		mkdir( $this->temp_dir );
		echo "Temporary dir: {$this->temp_dir}" . PHP_EOL;
	}

	public function checkout_svn_trunk_readme( $slug ) {
		// Time to checkout the SVN tree.
		echo "Checking out trunk README from SVN tree at: {$this->config_settings['svn-url']}/trunk/readme.txt...";
		
		// Note, you cannot checkout a single file from SVN, but you can limit how deep you go so "--depth files" is added 
		// below to avoid checking out a lot of cruft from large plugins that we don't need.
		exec( '"' . $this->config_settings['svn-path'] . 'svn" co "' . $this->config_settings['svn-url'] . '/trunk" "' . $this->temp_dir . '" --depth files' .  $this->platform_null, $output, $result );

		if( $result ) {
			echo " error, SVN checkout failed.";
			return false;
		} else {
			echo ' done.'  . PHP_EOL;
			return true;
		}
	}

	public function load_readme( $slug ) {
		echo 'Loading README in to memory.' . PHP_EOL;

		// Load the readme file in to memory and split it up by lines.
		$this->readme = file_get_contents( $this->temp_dir . '/readme.txt' );
		$this->readme_eol = $this->detect_eol_type( $this->readme );
		$this->readme = explode( $this->readme_eol, $this->readme );
	}
	
	public function replace_wp_version( $slug ) {
		$updated = false;
		$notfound = true;
		
		// Loop through the readme lines.
		for( $i = 0; $i < count( $this->readme ); $i++ ) {
			// The header lines are complete when we run in to the first blank line so we can stop looking.
			if( trim( $this->readme[$i] ) == '' ) {
				break;
			}
			
			// Split each readme line based on the colon.
			$parsed = explode( ':', $this->readme[$i] );
		
			// Check to see if this is the 'tested up to' line and process it if so.
			if( strtolower( $parsed[0] ) === 'tested up to' && count( $parsed ) > 1 ) {
				$notfound = false;
				
				if( trim( $parsed[1] ) !== $this->latest_wp_version ) {
					echo 'Updating \'Tested up to\' line in README.' . PHP_EOL;
					$this->readme[$i] = 'Tested up to: ' . $this->latest_wp_version;
					$updated = true;
				} else {
					echo '\'Tested up to\' line in README is already current.' . PHP_EOL;
				}
			}

			// Check to see if this is the 'stable tag' line and process it if so.
			if( strtolower( $parsed[0] ) === 'stable tag' && count( $parsed ) > 1 ) {
				$this->stable_tag = trim( $parsed[1] );
			}
		}

		if( $updated == false && $notfound ) {
			echo '\'Tested up to\' line not found in README!' . PHP_EOL;
		}
		
		return $updated;
	}
	
	public function write_readme( $slug ) {
		echo 'Writing README to file.' . PHP_EOL;
		file_put_contents( $this->temp_dir . $this->readme_path . '/readme.txt', implode( $this->readme_eol, $this->readme ) );
	}
	
	public function commit_svn_changes( $slug ) {
		if( $this->confirm_commit() ) {
			echo 'Committing to SVN...';
			exec( '"' . $this->config_settings['svn-path'] . 'svn" commit -m "' . $this->config_settings['svn-commit-message'] . '" "' . $this->temp_dir . '/readme.txt"', $output, $result );

			if( $result ) {
				echo " error, commit failed." . PHP_EOL;
			} else {
				echo ' done!' . PHP_EOL;
			}
		} else {
			echo 'Commit aborted.' . PHP_EOL;
		}
	}

	public function get_stable_tag( $slug ) {
		if( $this->stable_tag !== 'trunk' && $this->stable_tag !== '' ) {
			echo 'Stable tag is "' . $this->stable_tag . '".' . PHP_EOL;
		
			return true;
		} else {
			return false;
		}
	}

	public function checkout_svn_stable_tag_readme( $slug ) {
		// Time to checkout the SVN tree.
		echo "Checking out stable tag README from SVN tree at: {$this->config_settings['svn-url']}/tags/{$this->stable_tag}...";

		// Note, you cannot checkout a single file from SVN, but you can limit how deep you go so "--depth files" is added 
		// below to avoid checking out a lot of cruft from large plugins that we don't need.
		exec( '"' . $this->config_settings['svn-path'] . 'svn" co "' . $this->config_settings['svn-url'] . '/tags/' . $this->stable_tag . '" "' . $this->temp_dir . '" --depth files' .  $this->platform_null, $output, $result );

		if( $result ) {
			echo " error, SVN checkout failed." . PHP_EOL;
			return false;
		} else {
			echo ' done.'  . PHP_EOL;
			return true;
		}
	}

	public function cleanup_after_commit( $slug ) {
		$this->clean_up();
		
		// Add a slight delay as sometimes the delete takes a second for the file system to catch up with.
		sleep(1);
		
		mkdir( $this->temp_dir );
	}		
		
	public function clean_up() {
		if( false !== $this->temp_dir ) {
			// Clean up the temporary dirs/files.
			$this->delete_tree( $this->temp_dir );
		}
	}

	/*
	 *
	 * Private functions
	 *
	 */

	 private function confirm_commit() {
		// Comment the following line to display a confirmation prompt.
		// Used for debugging only.
		return true;
	 
		echo PHP_EOL;
		echo "About to commit README. Double-check {$this->temp_dir}{$this->readme_path}/readme.txt to make sure everything looks fine." . PHP_EOL;
		echo PHP_EOL;
		echo "Type 'YES' in all capitals and then return to continue." . PHP_EOL;

		$fh = fopen( 'php://stdin', 'r' );
		$message = fgets( $fh, 1024 ); // read the special file to get the user input from keyboard
		fclose( $fh );

		if( trim( $message ) === 'YES' ) {
			return true;
		}
		
		return false;
	}

	private function delete_tree( $dir ) {
		if( ! is_dir( $dir ) ) {
			return true;
		}

		// unlink is broken on Windows, it does not always unlink a file even if you have permissions
		// based on if it is hidden/system/archive/readonly, so make sure all flags are cleared before
		// unlinking.  Normally we could do this at the top level of the directory and process it with
		// attrib's "/s /d" options, but that is also broken on SVN trees, so do it for each directory
		// we're processing.
		if( $this->platform == 'win' ) {
			exec( "attrib -s -h -a -r \"$dir/*\"" );
		}
		
		$files = array_diff( scandir( $dir ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			if( is_dir( "$dir/$file" ) ) {
				$this->delete_tree("$dir/$file");
			} else {
				unlink("$dir/$file");
			}
		}

		return rmdir( $dir );
	}

	private function get_file_list( $dir ) {
		$files = array_diff( scandir( $dir ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			if( is_dir( "$dir/$file" ) ) {
				array_merge( $files, $this->get_file_list("$dir/$file") );
			}
		}

		return $files;
	}

	private function release_replace_placeholders( $string, $placeholders ) {
		if( ! is_array( $placeholders ) ) {
			return $string;
		}
		
		foreach( $placeholders as $tag => $value ) {
			$string = preg_replace( '/{{' . $tag . '}}/i', $value, $string );
		}

		return $string;
	}
	
	private function error_and_exit( $message ) {
		echo $message . PHP_EOL;
		
		$this->clean_up();

		exit;
	}

	private function set_current_wp_version() {
		$response = file_get_contents( 'https://api.wordpress.org/core/version-check/1.6/' );

		$version_info = unserialize( $response );

		if( is_array( $version_info ) && array_key_exists( 'offers', $version_info ) && is_array( $version_info['offers'] ) ) {
			foreach( $version_info['offers'] as $offer ) {
				if( is_array( $offer ) ) {
					if( version_compare( $this->latest_wp_version, $offer['current'], '<' ) ) {
						$this->latest_wp_version = $offer['current'];
					}
				}
			}
		}
	}
	
	private function detect_eol_type( $text ) {
		if( '' == $text ) { return "\n"; }
		
		list( $first, $rest ) = explode( "\n", $text, 2 );
		
		if( substr( $first, -1, 1 ) === "\r" ) {
			return "\r\n";
		} else {
			return "\n";
		}
	}
	
}
