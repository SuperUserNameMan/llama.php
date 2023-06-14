<?php // TAB = 4


class LLaMaTokenList
{
	public  object $C_DATA ;

	private int    $C_LAST = 0 ;
	private int    $RESIZE_STEP = 2048 ;

	function __construct( int $RESERVED_SIZE = 4096 , int $RESIZE_STEP = 2048 )
	{
		$RESERVED_SIZE = ( 1 + (int)( $RESERVED_SIZE / $this->RESIZE_STEP ) ) * $this->RESIZE_STEP ;

		$this->C_DATA = FFI::new( 'int['.$RESERVED_SIZE.']' );

		$this->RESIZE_STEP = $RESIZE_STEP ;
	}

	public function reserve( int $DESIRED_SIZE ) : bool
	{
		return $this->resize( $DESIRED_SIZE , false );
	}

	public function resize( int $DESIRED_SIZE , bool $RECOPY = true ) : bool
	{
		$NEW_SIZE = ( 1 + (int)( $DESIRED_SIZE / $this->RESIZE_STEP ) ) * $this->RESIZE_STEP ;

		if ( $DESIRED_SIZE == count( $this->C_DATA ) ) return true ;

		$NEW_DATA = FFI::new( 'int['.$NEW_SIZE.']' );

		if ( $RECOPY )
		{
			FFI::memcpy( $NEW_DATA , $this->C_DATA , min( $DESIRED_SIZE , $this->C_LAST ) );
			$this->C_LAST = min( $this->C_LAST , $DESIRED_SIZE );
		}
		else
		{
			$this->C_LAST = 0 ;
		}

		$this->C_DATA = $NEW_DATA ;

		return count( $NEW_DATA ) >= $DESIRED_SIZE ;
	}

	private function auto_resize() : bool
	{
		if ( $this->C_LAST < count( $this->C_DATA ) ) return false ;

		$NEW_SIZE = count( $this->C_DATA ) + $this->RESIZE_STEP ;

		return $this->resize( $NEW_SIZE );
	}

	public function seek( int $POS ) : bool
	{
		if ( $POS < 0 || $POS >= count( $this->C_DATA ) ) return false ;

		$this->C_LAST = $POS ;

		return true ;
	}

	public function push( int $TOKEN ) : void
	{
		$this->C_DATA[ $this->C_LAST ] = $TOKEN ;
		$this->C_LAST++;

		$this->auto_resize();
	}

	public function pop() : int
	{
		if ( $this->C_LAST == 0 ) return 0 ;

		$this->C_LAST--;

		return $this->C_DATA[ $this->C_LAST ];
	}


	public function clear() : void
	{
		$this->C_LAST = 0 ;
		$this->C_DATA[ 0 ] = 0 ;
	}

	// How many token are actually contained into this list :
	public function count() : int { return $this->C_LAST; }

	// How many tokens this list could contain without being resized :
	public function reserved() : int { return count( $this->C_DATA ); }

	//
	public function addr( int $AT = 0 ) : object { return FFI::addr( $this->C_DATA[ $AT ] ); }
}

// EOF
