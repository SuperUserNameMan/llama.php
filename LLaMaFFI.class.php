<?php
//TAB=4

require_once('LLaMaTokenList.class.php');
require_once('LLaMaCandidateTokens.class.php');

final class LLaMaFFI
{
	//----------------------------
	// static tool functions :
	//----------------------------

	static public function CPU_CORES() : int
	{
		static $CPU_CORES = 0 ;

		if ( $CPU_CORES == 0 )
		{
			$CPUINFO = file_get_contents( '/proc/cpuinfo' , 'r' );
			preg_match( '/cpu cores	: ([0-9]+)/' , $CPUINFO , $FOUND );
			$CPU_CORES = $FOUND[1];
		}

		return $CPU_CORES ;
	}

	static public function CPU_PROCS() : int
	{
		static $CPU_PROCS = 0 ;

		if ( $CPU_PROCS == 0 )
		{
			$CPUINFO = file_get_contents( '/proc/cpuinfo' , 'r' );
			preg_match( '/siblings	: ([0-9]+)/' , $CPUINFO , $FOUND );
			$CPU_PROCS = $FOUND[1];
		}

		return $CPU_PROCS ;
	}

	static public function compile_lib( string $llama_cpp_source_path = './llama.cpp' )
	{
		unlink('./libllama.so');
		system("make -C $llama_cpp_source_path clean");
		system("make -C $llama_cpp_source_path libllama.so LLAMA_OPENBLAS=1");
		link('./llama.cpp/libllama.so' , './libllama.so' );
	}

	static private function ARRAY_TO_STRUCT( array $ARRAY , object $STRUCT ) : bool
	{
		$FIELDS = FFI::typeof( $STRUCT )->getStructFieldNames() ;

		$UNKOWN_FIELD = false ;

		foreach( $ARRAY as $PARAM => $VALUE )
		{
			if ( in_array( $PARAM , $FIELDS ) )
			{
				if ( is_array( $VALUE ) )
				{
					foreach( $VALUE as $INDEX => $ITEM )
					{
						$STRUCT->$PARAM[ $INDEX ] = $ITEM ;
					}
				}
				else
				{
					$STRUCT->$PARAM = $VALUE ;
				}
			}
			else
			{
				$UNKOWN_FIELD = true ;
			}
		}

		return $UNKOWN_FIELD ;
	}


	//---------------------------
	// enums & consts :
	//---------------------------

	const LLAMA_SESSION_MAGIC   = 0x6767736e ; // 'ggsn'
	const LLAMA_SESSION_VERSION = 1 ;

	// enum llama_ftype {
		const FTYPE_ALL_F32              = 0 ;
		const FTYPE_MOSTLY_F16           = 1 ; // except 1d tensors
		const FTYPE_MOSTLY_Q4_0          = 2 ; // except 1d tensors
		const FTYPE_MOSTLY_Q4_1          = 3 ; // except 1d tensors
		const FTYPE_MOSTLY_Q4_1_SOME_F16 = 4 ; // tok_embeddings.weight and output.weight are F16
		//const FTYPE_MOSTLY_Q4_2          = 5 ; // support has been removed
		//const FTYPE_MOSTLY_Q4_3          = 6 ; // support has been removed
		const FTYPE_MOSTLY_Q8_0          = 7 ; // except 1d tensors
		const FTYPE_MOSTLY_Q5_0          = 8 ; // except 1d tensors
		const FTYPE_MOSTLY_Q5_1          = 9 ; // except 1d tensors
		const FTYPE_MOSTLY_Q2_K          = 10 ; // except 1d tensors
		const FTYPE_MOSTLY_Q3_K_S        = 11 ; // except 1d tensors
		const FTYPE_MOSTLY_Q3_K_M        = 12 ; // except 1d tensors
		const FTYPE_MOSTLY_Q3_K_L        = 13 ; // except 1d tensors
		const FTYPE_MOSTLY_Q4_K_S        = 14 ; // except 1d tensors
		const FTYPE_MOSTLY_Q4_K_M        = 15 ; // except 1d tensors
		const FTYPE_MOSTLY_Q5_K_S        = 16 ; // except 1d tensors
		const FTYPE_MOSTLY_Q5_K_M        = 17 ; // except 1d tensors
		const FTYPE_MOSTLY_Q6_K          = 18 ; // except 1d tensors
	// }

	//------------------------
	// static properties :
	//------------------------

	private static $FFI = null ;

	private static $CTX = null ; // TODO : allow more than one model one the same system ?
	private static $CTX_PARAMS = null ;
	private static $CTX_SEED = 0 ;

	private static string $MODEL_PATH = '' ;
	private static string $LORA_PATH = '' ;

	//------------------------
	// object properties :
	//------------------------

	public LLaMaCandidateTokens $CANDIDATES ;

	//-------------------------------
	// Constructor & Destructor :
	//-------------------------------

	function __construct()
	{
		if ( is_null( self::$FFI ) )
		{
			self::$FFI = FFI::load( './LLaMaFFI.h' );
			self::$FFI->llama_init_backend();
		}
	}

	function __destruct()
	{
		$this->free();
	}



	//------------------------------
	// Interface to the C API :
	//------------------------------

	public function context_default_params() : object { return self::$FFI->llama_context_default_params(); }
	public function model_quantize_default_params() : object { return self::$FFI->llama_model_quantize_default_params(); }

	public function mmap_supported() : bool { return self::$FFI->llama_mmap_supported(); }
	public function mlock_supported() : bool { return self::$FFI->llama_mlock_supported(); }

	public function time_us() : int { return self::$FFI->llama_time_us(); }

	public function init_from_file( string $MODEL_PATH , array $PARAMS = [] ) : bool
	{
		$this->free();

		if ( ! file_exists( $MODEL_PATH ) )
		{
			trigger_error( 'Error in '.__CLASS__.'::'.__METHOD__.'() : model not found at '.$MODEL_PATH );
			return false ;
		}

		self::$CTX_PARAMS = self::$FFI->llama_context_default_params();

		self::ARRAY_TO_STRUCT( $PARAMS , self::$CTX_PARAMS );

		self::$CTX = self::$FFI->llama_init_from_file( $MODEL_PATH , self::$CTX_PARAMS );

		if ( is_null( self::$CTX ) )
		{
			trigger_error( 'Error in '.__CLASS__.'::'.__METHOD__.'() : llama_init_from_file() failed' );
			return false ;
		}

		self::$MODEL_PATH = $MODEL_PATH ;

		$this->CANDIDATES = new LLaMaCandidateTokens( self::$FFI , self::$CTX );

		return true ;
	}

	public function free() : bool
	{
		if ( is_null( self::$CTX ) ) return false ;

		self::$FFI->llama_free( self::$CTX );
		self::$CTX = null ;

		self::$MODEL_PATH = '' ;
		self::$LORA_PATH = '' ;

		return true ;
	}

	public function model_quantize( string $FNAME_INPUT , string $FNAME_OUTPUT , array $MODEL_QUANTIZE_PARAMS ) : bool
	{
		//XXX untested

		$PARAMS = self::$FFI->llama_model_quantize_default_params();

		self::ARRAY_TO_STRUCT( $MODEL_QUANTIZE_PARAMS , $PARAMS );

		if ( self::$FFI->llama_model_quantize( $FNAME_INPUT , $FNAME_OUTPUT , FFI::addr( $PARAMS ) ) )
		{
			trigger_error( 'Error in '.__CLASS__.'::'.__METHOD__.'() : llama_model_quantize() failed' );
			return false ;
		}

		return true ;
	}

	public function apply_lora_from_file( string $LORA_PATH , string|null $LORA_BASE = null , int $N_THREADS = 0 ) : bool
	{
		//XXX untested

		if ( $N_THREADS <= 0 ) $_NTHREADS = self::CPU_CORES();

		if ( is_null( self::$CTX ) ) return false ;

		if ( self::$FFI->llama_apply_lora_from_file( self::$CTX , $LORA_PATH , $LORA_BASE , $N_THREADS ) )
		{
			trigger_error( 'Error in '.__CLASS__.'::'.__METHOD__.'() : llama_apply_lora_from_file() failed' );
			return false ;
		}

		return true ;
	}

	public function get_kv_cache_token_count() : int
	{
		return self::$FFI->llama_get_kv_cache_token_count( self::$CTX );
	}

	public function set_rng_seed( int $SEED = -1 ) : int
	{
		if ( $SEED < 0 ) $SEED = time();

		self::$FFI->llama_set_rng_seed( self::$CTX , $SEED );

		self::$CTX_SEED = $SEED ;

		return $SEED ;
	}

	public function get_rng_seed() : int { return self::$CTX_SEED ; }

	public function get_state_size() : int { return self::$FFI->llama_get_state_size( self::$CTX ); }

	public function copy_state_data( object $DEST_C_BYTE_BUFFER = null ) : object|false
	{
		// XXX untested

		$STATE_SIZE = self::$FFI->llama_get_state_size( self::$CTX ) ;

		$DEST_C_BYTE_BUFFER ??= FFI::new( FFI::arrayType( FFI::type('uint8_t') , $STATE_SIZE ) );

		if ( count( $DEST_C_BYTE_BUFFER ) < $STATE_SIZE )
		{
			trigger_error( 'Error in '.__CLASS__.'::'.__METHOD__.'() :'
					.' destination buffer too small'
					.' ( '.count( $DEST_C_BYTE_BUFFER ).' provided ; '.$STATE_SIZE.' required )'
			);
			return false ;
		}

		$RES = self::$FFI->llama_copy_state_data( self::$CTX , FFI::addr( $DEST_C_BYTE_BUFFER[0] ) );

		if ( $RES > count( $DEST_C_BYTE_BUFFER ) )
		{
			trigger_error( 'Error in '.__CLASS__.'::'.__METHOD__.'() :'
					.' more bytes copied than allocated.'
					.' ( '.$RES.' copied ; '.count( $DEST_C_BYTE_BUFFER ).' allocated )'
			);
		}

		return $DEST_C_BYTE_BUFFER ;
	}

	public function set_state_data( object $SOURCE_C_BYTE_BUFFER ) : bool
	{
		// XXX untested

		$STATE_SIZE = self::$FFI->llama_get_state_size( self::$CTX ) ;

		if ( count( $SOURCE_C_BYTE_BUFFER ) < $STATE_SIZE )
		{
			trigger_error( 'Error in '.__CLASS__.'::'.__METHOD__.'() :'
					.' source buffer too small'
					.' ( '.count( $SOURCE_C_BYTE_BUFFER ).' provided ; '.$STATE_SIZE.' required )'
			);
			return false ;
		}

		$RES = self::$FFI->llama_set_state_data( self::$CTX , FFI::addr( $SOURCE_C_BYTE_BUFFER[0] ) );

		if ( $RES > count( $DEST_C_BYTE_BUFFER ) )
		{
			trigger_error( 'Error in '.__CLASS__.'::'.__METHOD__.'() :'
					.' more bytes copied than provided.'
					.' ( '.$RES.' copied ; '.count( $DEST_C_BYTE_BUFFER ).' provided )'
			);

			return false ;
		}

		return true ;
	}


	public function load_session( string $PATH , LLaMaTokenList $TOKENS , string $EXT = '.llama-session' ) : bool
	{
		if ( ! str_starts_with( $EXT , '.' ) ) $EXT = '.'.$EXT;
		if ( ! str_ends_with( $PATH , $EXT ) ) $PATH .= $EXT ;

		if ( ! file_exists( $PATH ) )
		{
			trigger_error( 'Error in '.__CLASS__.'::'.__METHOD__.'() :'
					.' could not load session from : '.$PATH
			);
			return false ;
		}

		$HEADER = self::$FFI->new( '_llama_session_file_header' );
		$HEADER_SIZE = FFI::sizeof( $HEADER );

		$FILE = fopen( $PATH , 'r' );
		$HEADER_STR = fread( $FILE , $HEADER_SIZE );
		fclose( $FILE );

		FFI::memcpy( FFI::addr( $HEADER ) , $HEADER_STR , $HEADER_SIZE );

		if ( $HEADER->magic != self::LLAMA_SESSION_MAGIC || $HEADER->version != self::LLAMA_SESSION_VERSION )
		{
			trigger_error( 'Error in '.__CLASS__.'::'.__METHOD__.'() :'
					.' not a valid session file format : '.$PATH
			);
			return false ;
		}

		$TOKENS->reserve( $HEADER->n_tokens_count );

		$_TOKEN_COUNT = FFI::new('size_t');

		$RES = self::$FFI->llama_load_session_file( self::$CTX , $PATH , $TOKENS->addr() , $TOKENS->reserved() , FFI::addr( $_TOKEN_COUNT ) );

		$TOKENS->seek( $HEADER->n_tokens_count );

		if ( ! $RES )
		{
			trigger_error( 'Error in '.__CLASS__.'::'.__METHOD__.'() :'
					.' could not load session file : '.$PATH
			);
		}

		return $RES ;
	}

	public function save_session( string $PATH , LLaMaTokenList $TOKENS , string $EXT = '.llama-session' ) : bool
	{
		if ( ! str_starts_with( $EXT , '.' ) ) $EXT = '.'.$EXT;
		if ( ! str_ends_with( $PATH , $EXT ) ) $PATH .= $EXT ;

		$RES = self::$FFI->llama_save_session_file( self::$CTX , $PATH , $TOKENS->addr() , $TOKENS->count() );

		if ( ! $RES )
		{
			trigger_error( 'Error in '.__CLASS__.'::'.__METHOD__.'() :'
					.' could not save session to : '.$PATH
			);
		}

		return $RES ;
	}


	public function eval( LLaMaTokenList $TOKENS , int $N_PAST = 0 , int $N_THREADS = -1 ) : bool
	{
		if ( $N_THREADS <= 0 ) $_NTHREADS = self::CPU_CORES();

		$RES = self::$FFI->llama_eval( self::$CTX , $TOKENS->addr() , $TOKENS->count() , $N_PAST , $_NTHREADS );

		if ( $RES != 0 )
		{
			trigger_error( 'Error in '.__CLASS__.'::'.__METHOD__.'() : llama_eval() failed' );
			return false ;
		}

		return true ;
	}

	public function tokenize( string $TEXT , bool $ADD_BOS , LLaMaTokenList $TOKENS = null ) : LLaMaTokenList
	{
		$NEED_SIZE = strlen( $TEXT ) + (int)$ADD_BOS ;

		$TOKENS ??= new LLaMaTokenList( $NEED_SIZE );

		$TOKENS->reserve( $NEED_SIZE );

		$N_TOKENS = self::$FFI->llama_tokenize( self::$CTX , $TEXT , $TOKENS->addr() , $TOKENS->reserved() , $ADD_BOS );

		$TOKENS->seek( $N_TOKENS );

		return $TOKENS ;
	}

	public function n_vocab() : int
	{
		return self::$FFI->llama_n_vocab( self::$CTX );
	}

	public function n_ctx() : int
	{
		return self::$FFI->llama_n_ctx( self::$CTX );
	}

	public function n_embd() : int
	{
		return self::$FFI->llama_n_embd( self::$CTX );
	}

	public function get_logits() : object
	{
		return self::$FFI->llama_get_logits( self::$CTX );
	}

	public function get_embeddings() : object
	{
		return self::$FFI->llama_get_embeddings( self::$CTX );
	}

	public function token_to_str( int $TOKEN ) : string
	{
		return self::$FFI->llama_token_to_str( self::$CTX , $TOKEN );
	}

	public function tokens_to_str( LLaMaTokenList $TOKENS ) : string
	{
		$STR = '';

		for( $i = 0 ; $i < $TOKENS->count() ; $i++ )
		{
			$STR .= $this->token_to_str( $TOKENS->C_DATA[ $i ] );
		}

		return $STR ;
	}

	public function token_bos() : int { return self::$FFI->llama_token_bos(); }
	public function token_eos() : int { return self::$FFI->llama_token_eos(); }
	public function token_nl()  : int { return self::$FFI->llama_token_nl(); }

	public function untokenize( LLaMaTokenList $TOKENS ) : array
	{
		$OUT = [];

		for( $i = 0 ; $i < $TOKENS->count() ; $i++ )
		{
			$OUT[] = $this->token_to_str( $TOKENS->C_DATA[ $i ] );
		}

		return $OUT ;
	}
}

// EOF
