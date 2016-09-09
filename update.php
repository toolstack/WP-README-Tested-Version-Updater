<?php
/* update.php
 *
 * Updates the readme.txt file on wordpress.org to have the current WP release version in the "Tested up to:" line.
 *
 */
 
include_once( 'class.update.php' );

$update_script = new update;

// Process the command line, config settings and setup our temp directory.
$update_script->process_args();
$update_script->get_config();
$update_script->set_temp_dir();

// Loop through each slug we are going to process.
foreach( $update_script->slugs as $slug ) {
	echo PHP_EOL;
	echo "Processing {$slug}..." . PHP_EOL;
	
	// Customize our configuration settings based on the current slug we're processing.
	$update_script->set_config_settings( $slug );
	
	// Go get the trunk readme.txt from SVN and load it in memory.
	$update_script->checkout_svn_trunk_readme( $slug );
	$update_script->load_readme( $slug );

	// Replace the "Test up to:" line with the current WP version, the return value is if there was a change or not.
	if( $update_script->replace_wp_version( $slug ) ) {
		// Since we changed the value, write the readme back to disk and commit the change to SVN.
		$update_script->write_readme( $slug );
		$update_script->commit_svn_changes( $slug );
		}

	$update_script->cleanup_after_commit( $slug );
	
	// Check to see if the readme defined a "Stable Tag:" line, if so update the readme in that tag as well.
	if( $update_script->get_stable_tag( $slug ) ) {
		// Go get the tag readme.txt from SVN and load it in memory.
		$update_script->checkout_svn_stable_tag_readme( $slug );
		$update_script->load_readme( $slug );
		
		// Replace the "Test up to:" line with the current WP version, the return value is if there was a change or not.
		if( $update_script->replace_wp_version( $slug ) ) {
			// Since we changed the value, write the readme back to disk and commit the change to SVN.
			$update_script->write_readme( $slug );
			$update_script->commit_svn_changes( $slug );
		}
	}
	
	$update_script->cleanup_after_commit( $slug );
}

$update_script->clean_up();