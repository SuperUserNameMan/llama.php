# llama.php
PHP 8 bindings to [ `llama.cpp` ](https://github.com/ggerganov/llama.cpp) using FFI

it's WIP

Tested on PHP 8.1.2 on linux.

`compile_libllama.php` will automatically :
- clone and pull [llama.cpp](https://github.com/ggerganov/llama.cpp) ;
- generate/update `llama.ffi.h` and `llama.ffi.php` ;
- compile one or several versions of `libllama.so` (see config parameters into the script) ;

Todo :
- [ ] create `llama.class.php` 

