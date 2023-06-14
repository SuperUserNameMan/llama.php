<?php
//TAB=4

require_once('LLaMaFFI.class.php');

$LLAMA = new LLaMaFFI();

$LLAMA->init_from_file( './models/ggml-vic7b-uncensored-q5_1.bin' , [ 'n_ctx' => 128 , 'logits_all' => false ] );

echo "CPU cores : ".$LLAMA::CPU_CORES().PHP_EOL;
echo "CPU procs : ".$LLAMA::CPU_PROCS().PHP_EOL;


$PROMPT = "Hello my name is";

$TOK_INPUT = $LLAMA->tokenize( $PROMPT , true ) ;



if ( $TOK_INPUT->count() > $LLAMA->n_ctx() - 4 )
{
	echo "ERROR : Prompt is too long.".PHP_EOL;
	exit();
}

//$LLAMA->save_session( 'test' , $TOK_INPUT );
//$LLAMA->load_session( 'test' , $TOK_INPUT );

//print_r( $LLAMA->untokenize( $TOK_INPUT ) );

echo $PROMPT;

while( $LLAMA->get_kv_cache_token_count() < $LLAMA->n_ctx() )
{
	$LLAMA->eval( $TOK_INPUT , $LLAMA->get_kv_cache_token_count() ) ;

	$LLAMA->CANDIDATES->update();

	$TOK = $LLAMA->CANDIDATES->select_token();

	echo $LLAMA->token_to_str( $TOK );

	$TOK_INPUT->clear();
	$TOK_INPUT->push( $TOK );
}

echo PHP_EOL;


// EOF
