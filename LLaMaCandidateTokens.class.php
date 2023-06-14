<?php // TAB = 4

class LLaMaCandidateTokens
{
	public object $FFI ;
	public object $CTX ;

	public object $CANDIDATES ;

	function __construct( object $FFI , object $CTX )
	{
		$this->FFI = $FFI ;
		$this->CTX = $CTX ;
		$this->CANDIDATES = $FFI->new( 'llama_token_data_array' );

		$this->CANDIDATES->data = FFI::addr( $FFI->new('llama_token_data['.$FFI->llama_n_vocab( $CTX ).']')[0] );
		$this->CANDIDATES->size = $FFI->llama_n_vocab( $CTX );
		$this->CANDIDATES->sorted = false ;
	}

	public function update() : self
	{
		$LOGITS = $this->FFI->llama_get_logits( $this->CTX );
		$N_VOCA = $this->FFI->llama_n_vocab( $this->CTX );

		for( $i = 0 ; $i < $N_VOCA ; $i++ )
		{
			$this->CANDIDATES->data[ $i ]->id = $i ;
			$this->CANDIDATES->data[ $i ]->logit = $LOGITS[ $i ];
			$this->CANDIDATES->data[ $i ]->p = 0.0 ;
		}

		$this->CANDIDATES->sorted = false ;

		return $this ;
	}

	//-------------------------
	// candidates filterings :
	//-------------------------

	public function apply_repetition_penalty( TokenList $RECENT_TOKENS_HISTORY , float $PENALTY  ) : self
	{
		$this->FFI->llama_sample_repetition_penalty( $this->CTX ,
				FFI::addr( $this->CANDIDATES ) ,
				$RECENT_TOKENS_HISTORY->addr() ,
				$RECENT_TOKENS_HISTORY->count() ,
				$PENALTY
		);

		return $this ;
	}

	public function apply_frequency_and_presence_penalties( TokenList $RECENT_TOKENS_HISTORY , float $ALPHA_FREQUENCY , float $ALPHE_PRESENCE ) : self
	{
		$this->FFI->llama_sample_frequency_and_presence_penalties( $this->CTX ,
				FFI::addr( $this->CANDIDATES ),
				$RECENT_TOKENS_HISTORY->addr() ,
				$RECENT_TOKENS_HISTORY->count() ,
				$ALPHA_FREQUENCY ,
				$ALPHE_PRESENCE
		);

		return $this ;
	}

	public function apply_softmax() : self
	{
		$this->FFI->llama_sample_softmax( $this->CTX , FFI::addr( $this->CANDIDATES ) );
		return $this ;
	}

	public function apply_top_k( int $K , int $MIN_KEEP ) : self
	{
		$this->FFI->llama_sample_top_k( $this->CTX , FFI::addr( $this->CANDIDATES ) , $K , $MIN_KEEP );
		return $this ;
	}

	public function apply_top_p( float $P , int $MIN_KEEP ) : self
	{
		$this->FFI->llama_sample_top_p( $this->CTX , FFI::addr( $this->CANDIDATES ) , $P , $MIN_KEEP );
		return $this ;
	}

	public function apply_tail_free( float $Z , int $MIN_KEEP ) : self
	{
		$this->FFI->llama_sample_tail_free( $this->CTX , FFI::addr( $this->CANDIDATES ), $Z , $MIN_KEEP );
		return $this ;
	}

	public function apply_typical( float $P , int $MIN_KEEP ) : self
	{
		$this->FFI->llama_sample_typical( $this->CTX , FFI::addr( $this->CANDIDATES ) , $P , $MIN_KEEP );
		return $this ;
	}

	public function apply_temperature( float $TEMP ) : self
	{
		$this->FFI->llama_sample_temperature( $this->CTX , FFI::addr( $this->CANDIDATES ) , $TEMP );
		return $this ;
	}

	//-----------------------------
	// candidate final selection :
	//-----------------------------

	public function select_mirostat( float $TAU , float $ETA , int $M , float &$MU ) : int
	{
		static $C_MU = null ;

		if ( is_null( $C_MU ) ) $C_MU = FFI::new('float[1]');

		$C_MU[0] = $MU ;

		$ID = $this->FFI->llama_sample_token_mirostat( $this->CTX , FFI::addr( $this->CANDIDATES ) , $TAU , $ETA , $M , FFI::addr( $C_MU ) );

		$MU = $C_MU[0];

		return $ID ;
	}

	public function select_mirostat2( float $TAU , float $ETA , float &$MU ) : int
	{
		static $C_MU = null ;

		if ( is_null( $C_MU ) ) $C_MU = FFI::new('float[1]');

		$C_MU[0] = $MU ;

		$ID = $this->FFI->llama_sample_token_mirostat_v2( $this->CTX , FFI::addr( $this->CANDIDATES ) , $TAU , $ETA , FFI::addr( $C_MU ) );

		$MU = $C_MU[0];

		return $ID ;
	}

	public function select_greedy() : int
	{
		$ID = $this->FFI->llama_sample_token_greedy( $this->CTX , FFI::addr( $this->CANDIDATES ) );

		return $ID ;
	}

	public function select_token() : int
	{
		$ID = $this->FFI->llama_sample_token( $this->CTX , FFI::addr( $this->CANDIDATES ) );

		return $ID ;
	}
}

// EOF
