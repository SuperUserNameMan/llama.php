<?php
//TAB=4

define( 'ENABLE_GIT' , 1 );
define( 'ENABLE_GIT_CLONE'  , 1 );
define( 'ENABLE_GIT_STATUS' , 1 );
define( 'ENABLE_GIT_PULL'   , 1 );

define( 'GIT_LLAMA_CPP'    , 'https://github.com/ggerganov/llama.cpp.git' );
define( 'DIR_TO_LLAMA_SOURCECODE' , './git/llama.cpp/' );

define( 'ENABLE_COMPILATION' , 1 );

$LLAMA_CPP_VERSIONS = [
	'default'  => 'LLAMA_FAST=1' ,
	'OpenBLAS' => 'LLAMA_FAST=1 LLAMA_OPENBLAS=1' ,
//	'cuBLAS'   => 'LLAMA_FAST=1 LLAMA_CUBLAS=1' ,
];

// ---------------------------------------------------------------------------------------------------------------------

function echo_stage( string $title )
{
	echo str_repeat( '-' , 80 ).PHP_EOL ;
	echo "\t$title".PHP_EOL;
	echo str_repeat( '-' , 80 ).PHP_EOL ;
}

// ---------------------------------------------------------------------------------------------------------------------

if ( ENABLE_GIT )
{
	if ( ENABLE_GIT_CLONE && ! is_dir( DIR_TO_LLAMA_SOURCECODE ) )
	{
		echo_stage( "Cloning ".GIT_LLAMA_CPP );

		system( 'git clone '.GIT_LLAMA_CPP.' '.DIR_TO_LLAMA_SOURCECODE , $R );
	}

	if ( ENABLE_GIT_STATUS && ! is_dir( DIR_TO_LLAMA_SOURCECODE ) )
	{
		echo_stage( "Checking ".DIR_TO_LLAMA_SOURCECODE );

		system( 'git -C '.DIR_TO_LLAMA_SOURCECODE.' status' , $R );

		if ( $R !== 0 )
		{
			trigger_error( 'Directory `'.DIR_TO_LLAMA_SOURCECODE.'` does not contain llama.cpp source code' , E_ERROR );
		}
	}

	if ( ENABLE_GIT_PULL )
	{
		echo_stage( "Updating ".DIR_TO_LLAMA_SOURCECODE );

		system( 'git -C '.DIR_TO_LLAMA_SOURCECODE.' pull' , $R );
	}
}

// ---------------------------------------------------------------------------------------------------------------------

if ( ! file_exists( DIR_TO_LLAMA_SOURCECODE.'/llama.h' ) &&  ! file_exists( DIR_TO_LLAMA_SOURCECODE.'/ggml.h' ) )
{
	trigger_error( "LLaMa.cpp source-code not found in `".DIR_TO_LLAMA_SOURCECODE."`" , E_ERROR );
}

$llama_h = file_get_contents( DIR_TO_LLAMA_SOURCECODE.'/llama.h' );
$ggml_h  = file_get_contents( DIR_TO_LLAMA_SOURCECODE.'/ggml.h' );

// ---------------------------------------------------------------------------------------------------------------------

echo_stage( "Building llama.ffi.h" );


$llama_ffi_h  = '// this PHP FFI header was generated using source code from : '.GIT_LLAMA_CPP.PHP_EOL.PHP_EOL;
$llama_ffi_h .= '// this script was generated automatically and might be overwritten on updates'.PHP_EOL;

preg_match( '|([ ]+enum ggml_type \{.+?[ ]+?\};)|s' , $ggml_h , $enum_ggml_type );

$llama_ffi_h .= $enum_ggml_type[0].PHP_EOL ;

$llama_ffi_h .= 'typedef void (*ggml_log_callback)(enum ggml_log_level level, const char * text, void * user_data);'.PHP_EOL;

$llama_ffi_h .= 'typedef struct {} FILE ;'.PHP_EOL;

$llama_h = preg_replace( '|^.*?extern "C" {[\r\n]+#endif|s'              , ''               , $llama_h );
$llama_h = preg_replace( '|#ifdef __cplusplus[\r\n]+}[\r\n]+#endif.*$|s' , ''               , $llama_h );
$llama_h = preg_replace( '| (LLAMA_API DEPRECATED\(.*?\);)|s'            , '/*** $1 ***/'   , $llama_h );
$llama_h = preg_replace( '|    LLAMA_API |'                              , '/*LLAMA_API*/ ' , $llama_h );

$llama_ffi_h .= $llama_h ;

file_put_contents( './llama.ffi.h' , $llama_ffi_h );

// ---------------------------------------------------------------------------------------------------------------------

echo_stage( "Building llama.ffi.php" );

if ( false === preg_match_all( '| [ ]+(LLAMA_[A-Z_0-9]+)[ ]+=[ ]*([0-9]+)|' , $llama_h , $llama_defs , PREG_SET_ORDER ) )
{
	trigger_error( "Could not extract constants from `llama.h`" , E_ERROR );
}

$llama_ffi_php = '<?php'.PHP_EOL ;

$llama_ffi_php .= '// this script was generated automatically and might be overwritten on updates'.PHP_EOL;

$previous_def_group = '' ;

foreach( $llama_defs as $definition )
{
	list( $_ , $const_name , $const_val ) = $definition ;

	$def_group = explode( '_' , $const_name )[1] ;

	if ( $def_group != $previous_def_group ) $llama_ffi_php .= PHP_EOL ;

	$llama_ffi_php .= "define( '$const_name' , $const_val );".PHP_EOL ;

	$previous_def_group = $def_group ;
}


$llama_ffi_php .= <<<'LLAMA_FFI_PHP'

// ----------- minimalist FFI loader -------------

function new_LLaMa_FFI( string $lib_path , string $header_path = 'llama.ffi.h' ) : object | false
{
	if ( ! file_exists( $lib_path ) ) return false ;

	if ( ! file_exists( $header_path ) ) return false ;

	$header = file_get_contents( $header_path );

	$FFI = FFI::cdef( $header , $lib_path );

	return $FFI ?? false ;
}

LLAMA_FFI_PHP;

$llama_ffi_php .= PHP_EOL.'//EOF';

file_put_contents( './llama.ffi.php' , $llama_ffi_php );

// ---------------------------------------------------------------------------------------------------------------------

if ( ENABLE_COMPILATION )
foreach( $LLAMA_CPP_VERSIONS as $VERSION_NAME => $VERSION_ARGS )
{
	echo_stage( "Compiling libllama_$VERSION_NAME.so" );

	system( 'make -C '.DIR_TO_LLAMA_SOURCECODE.' clean' , $R );

	system( 'make -C '.DIR_TO_LLAMA_SOURCECODE.' libllama.so '.$VERSION_ARGS , $R );

	if ( ! file_exists( DIR_TO_LLAMA_SOURCECODE.'/libllama.so' ) )
	{
		trigger_error( 'Compilation of `'.DIR_TO_LLAMA_SOURCECODE.'/libllama.so` failed' , E_ERROR );
	}

	copy( DIR_TO_LLAMA_SOURCECODE.'/libllama.so' , "libllama_$VERSION_NAME.so" );

	echo PHP_EOL ;
}

// ---------------------------------------------------------------------------------------------------------------------

foreach( $LLAMA_CPP_VERSIONS as $VERSION_NAME => $_ )
{
	$lib_path = "./libllama_$VERSION_NAME.so" ;

	echo_stage( "Testing $lib_path ..." );

	$ffi = FFI::cdef( $llama_ffi_h , $lib_path );

	echo $ffi->llama_print_system_info().PHP_EOL;
}

// ---------------------------------------------------------------------------------------------------------------------

echo_stage( "End of this script" );

//EOF
