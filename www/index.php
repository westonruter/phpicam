<?php

date_default_timezone_set( 'America/Los_Angeles' );

$sun_info = date_sun_info( time(), 45.5200, -122.6819 );

$is_daytime = (
	time() >= $sun_info['civil_twilight_begin']
	&&
	time() <= $sun_info['civil_twilight_end']
);

$default_args = array(
	'width' => 1024,
	'height' => 768,
	'max-age' => 10, // seconds
	'exposure' => $is_daytime ? 'auto' : 'verylong',
);
// todo hmac auth key

$input_args = array();
foreach ( array( 'width', 'height', 'max-age', 'refreshrate' ) as $numerical_key ) {
	if ( isset( $_GET[ $numerical_key ] ) && preg_match( '/^\d+$/', $_GET[ $numerical_key ] ) ) {
		$input_args[ $numerical_key ] = intval( $_GET[ $numerical_key ] );
	}
}

// Account for parameter in IP camera viewer app
if ( isset( $input_args['refreshrate'] ) ) {
	$input_args['max-age'] = $input_args['refreshrate'];
	unset( $input_args['refreshrate'] );
}

$args = array_merge( $default_args, $input_args );

$filename_args = $args;
unset( $filename_args['max-age'] );
$filename = sprintf( '/phpicam-cache/latest-%s.jpg', md5( serialize( $filename_args ) ) );

$take_picture = false;
if ( ! file_exists( $filename ) ) {
	$take_picture = true;
} else {
	$take_picture = ( filemtime( $filename ) + $args['max-age'] < time() );
}

if ( $take_picture ) {
	$cmd = 'raspistill';

	$cmd_args = $args;
	$cmd_args['output'] = $filename;
	unset( $cmd_args['max-age'] );
	$cmd .= ' --nopreview';

	foreach ( $cmd_args as $name => $value ) {
		$cmd .= ' ' . "--$name " . escapeshellarg( $value );
	}

	touch( $filename, time() + 24 * 60 * 60 ); // prevent concurrent camera requests by setting modified time far in future
	exec( $cmd, $output, $exit_code );
	if ( 0 !== $exit_code ) {
		if ( file_exists( $filename ) ) {
			unlink( $filename );
		}
		http_response_code( 500 );
		echo "Failed to execute: $cmd";
		exit;
	}

	# TODO: If memory is getting full, delete the other files from the cache
}

if ( ! is_readable( $filename ) ) {
	http_response_code( 500 );
	echo "Unable to read $filename";
	exit;
}

if ( 0 === filesize( $filename ) ) {
	http_response_code( 500 );
	echo "Empty file: $filename";
	exit;
}

http_response_code( 200 );
header( 'Content-Type: image/jpeg' );
header( sprintf( 'Content-Disposition: inline; filename=%s', gmdate( 'Ymd\THis' ) . 'Z.jpeg' ) );
header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s T', filemtime( $filename ) ) );
header( sprintf( 'Cache-Control: max-age=%d', $args['max-age'] ) );
echo file_get_contents( $filename );
