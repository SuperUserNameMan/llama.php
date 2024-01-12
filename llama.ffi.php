<?php
// this script was generated automatically and might be overwritten on updates

define( 'LLAMA_VOCAB_TYPE_SPM' , 0 );
define( 'LLAMA_VOCAB_TYPE_BPE' , 1 );

define( 'LLAMA_TOKEN_TYPE_UNDEFINED' , 0 );
define( 'LLAMA_TOKEN_TYPE_NORMAL' , 1 );
define( 'LLAMA_TOKEN_TYPE_UNKNOWN' , 2 );
define( 'LLAMA_TOKEN_TYPE_CONTROL' , 3 );
define( 'LLAMA_TOKEN_TYPE_USER_DEFINED' , 4 );
define( 'LLAMA_TOKEN_TYPE_UNUSED' , 5 );
define( 'LLAMA_TOKEN_TYPE_BYTE' , 6 );

define( 'LLAMA_FTYPE_ALL_F32' , 0 );
define( 'LLAMA_FTYPE_MOSTLY_F16' , 1 );
define( 'LLAMA_FTYPE_MOSTLY_Q4_0' , 2 );
define( 'LLAMA_FTYPE_MOSTLY_Q4_1' , 3 );
define( 'LLAMA_FTYPE_MOSTLY_Q4_1_SOME_F16' , 4 );
define( 'LLAMA_FTYPE_MOSTLY_Q8_0' , 7 );
define( 'LLAMA_FTYPE_MOSTLY_Q5_0' , 8 );
define( 'LLAMA_FTYPE_MOSTLY_Q5_1' , 9 );
define( 'LLAMA_FTYPE_MOSTLY_Q2_K' , 10 );
define( 'LLAMA_FTYPE_MOSTLY_Q3_K_S' , 11 );
define( 'LLAMA_FTYPE_MOSTLY_Q3_K_M' , 12 );
define( 'LLAMA_FTYPE_MOSTLY_Q3_K_L' , 13 );
define( 'LLAMA_FTYPE_MOSTLY_Q4_K_S' , 14 );
define( 'LLAMA_FTYPE_MOSTLY_Q4_K_M' , 15 );
define( 'LLAMA_FTYPE_MOSTLY_Q5_K_S' , 16 );
define( 'LLAMA_FTYPE_MOSTLY_Q5_K_M' , 17 );
define( 'LLAMA_FTYPE_MOSTLY_Q6_K' , 18 );
define( 'LLAMA_FTYPE_MOSTLY_IQ2_XXS' , 19 );
define( 'LLAMA_FTYPE_GUESSED' , 1024 );

define( 'LLAMA_ROPE_SCALING_NONE' , 0 );
define( 'LLAMA_ROPE_SCALING_LINEAR' , 1 );
define( 'LLAMA_ROPE_SCALING_YARN' , 2 );

define( 'LLAMA_GRETYPE_END' , 0 );
define( 'LLAMA_GRETYPE_ALT' , 1 );
define( 'LLAMA_GRETYPE_RULE_REF' , 2 );
define( 'LLAMA_GRETYPE_CHAR' , 3 );
define( 'LLAMA_GRETYPE_CHAR_NOT' , 4 );
define( 'LLAMA_GRETYPE_CHAR_RNG_UPPER' , 5 );
define( 'LLAMA_GRETYPE_CHAR_ALT' , 6 );

// ----------- minimalist FFI loader -------------

function new_LLaMa_FFI( string $lib_path , string $header_path = 'llama.ffi.h' ) : object | false
{
	if ( ! file_exists( $lib_path ) ) return false ;

	if ( ! file_exists( $header_path ) ) return false ;

	$header = file_get_contents( $header_path );

	$FFI = FFI::cdef( $header , $lib_path );

	return $FFI ?? false ;
}

//EOF