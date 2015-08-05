<?php 
  
#JMP:xkucha20

/**
  * @file jmp.php
  * 
  * @author Ondrej Kuchar
  *
  * @brief Simple macro-processor
  *
  * @detail School project implementing simple macro processor in PHP.
  */

/**
  * Global definitions
  */
  define('MACRO_NAME', 0);
  define('ESCAPE', 1);
  define('BLOCK', 2);
  define('OTHER', 3);
  define('END', 99);
  define('DEF', 10);
  define('LET', 11);
  define('SET', 12);
  define('NULL_TYPE', 13);
  define('USER', 14);

/**
  * Global vars
  */
  $input_stack = array();
  $macros = array();
  $macro_names = array();
  $input_spaces = true;

/**
  * Parses the param value
  * @param [in] $arg
  */
  function get_param_val($arg) {
    $hook = strpos($arg, "=", 0) + 1;
    return substr($arg, $hook, (strlen($arg) - $hook));
  }

/**
  * Parses all script params
  * @param [in] $argc
  * @param [in] $argv
  */
  function process_script_params($argc, $argv) {
    for ($i = 1; $i < $argc; $i++) {
      if (strcmp($argv[$i], "--help")  == false) {
        if($argc > 2) {
          fprintf(STDERR, "ERROR 1: Spatne pouziti parametru!\n");
          exit(1);
        }
        echo "TODO - napoveda\n\n";
        exit(0);
      }
      else if (strpos($argv[$i], "--input", 0) !== false) {
        if (defined('INPUT_PATH')) {
          fprintf(STDERR, "ERROR 1: Spatne pouziti parametru!\n");
          exit(1);  
        }
        define('INPUT_PATH', get_param_val($argv[$i]));
      }
      else if (strpos($argv[$i], "--output", 0) !== false) {
        if (defined('OUTPUT_PATH')) {
          fprintf(STDERR, "ERROR 1: Spatne pouziti parametru!\n");
          exit(1);  
        }
        define('OUTPUT_PATH', get_param_val($argv[$i]));
      }
      else if (strpos($argv[$i], "--cmd", 0) !== false) {
        if (defined('CMD')) {
          fprintf(STDERR, "ERROR 1: Spatne pouziti parametru!\n");
          exit(1);  
        }
        $cmd_text = get_param_val($argv[$i]);
        push_input($cmd_text);
        define('CMD', true);
      }
      else if (strcmp($argv[$i], "-r") == false) {
        if (defined('DENY_REDEFINE')) {
          fprintf(STDERR, "ERROR 1: Spatne pouziti parametru!\n");
          exit(1);  
        }
        define('DENY_REDEFINE', true);
      }
      else {
        fprintf(STDERR, "ERROR 1: Spatne pouziti parametru!\n");
        exit(1); 
      }
    }

    if (defined('INPUT_PATH')) {
      $input_stream = fopen(INPUT_PATH, "r");
      if($input_stream == false) {
        fprintf(STDERR, "ERROR 2: Nelze otevrit soubor pro cteni!\n");
        exit(2);
      }
    }
    else {
      $input_stream = STDIN;
    }
    

   if (defined('OUTPUT_PATH')) {
      $output_stream = fopen(OUTPUT_PATH, "w");
      if($output_stream == false) {
        fprintf(STDERR, "ERROR 3: Nelze otevrit soubor pro zapis!\n");
        fclose($input_stream);
        exit(3);
      }
    }
    else {
      $output_stream = STDOUT;
    }

    define('INPUT', $input_stream);
    define('OUTPUT', $output_stream);
  }

/**
  * Closes the files, prints the error messages and exits with correct error code
  * @param [in] $e
  */
  function close($e) {
    fclose(INPUT);
    fclose(OUTPUT);
    switch($e) {
      case 55:
        fprintf(STDERR, "ERROR 55: Syntakticka chyba!\n");
      break;
      case 56:
        fprintf(STDERR, "ERROR 56: Semanticka chyba!\n");
      break;
      case 57:
        fprintf(STDERR, "ERROR 57: Nepovolena redefinice makra!\n");
      break;
    }
    exit($e);
  }

/**
  * Inserts all built in macros into the table.
  */
  function prepare_table() {
    
    global $macros;
    global $macro_names;

    $built_in = array(
      array("@def", DEF),
      array("@let", LET),
      array("@set", SET),
      array("@null", NULL_TYPE),
      array("@__def__", DEF),
      array("@__let__", LET),
      array("@__set__", SET));
    foreach ($built_in as $macro) {
      $new_macro = new macro();
      $new_macro->name = $macro[0];
      $new_macro->type = $macro[1];
      $macros[$macro[0]] = $new_macro;
      array_push($macro_names, $new_macro->name);
    }
    $macros["@__def__"]->static = true;
    $macros["@__let__"]->static = true;
    $macros["@__set__"]->static = true;
  }

/**
  * Pushes input text to the global input stack (for example macro body after expanding)
  * @param [in] $text
  */
  function push_input($text) {
    global $input_stack;
    if (strlen($text)>0)
    array_push($input_stack, new input($text));
  }
/**
  * Reads character from appropriate input.
  * If the input stack is empty, read from the input stream.
  */
  function my_getchar() {
    global $input_stack;
    $stack_size = count($input_stack);
    if($stack_size == 0) {
      $c = fgetc(INPUT);
    }
    else {
      $c = $input_stack[$stack_size - 1]->next_char();
    }
    return $c;
  }

/*! An input class for handling the input data. */
  class input {

    private $data = ""; /*!< actual input data (string)*/
    private $len = 0; /*!< length of the data*/
    private $seeker = 0; /*!< seeker position in the data */

  /**
    * Class constructor sets the input and initial data.
    */
    function __construct($text) {
      $this->data = $text;
      $this->len = strlen($text);
      $this->seeker = 0;
    }

  /**
    * Member function returns specific character according to the seeker position.
    */
    function next_char() {
      global $input_stack;
      $c = $this->data[$this->seeker];
      $this->seeker++;
      if ($this->seeker == $this->len || $this->seeker > $this->len) {
        array_pop($input_stack);
      }
      return $c;
    }
  }

/**
  * Checks if the macro is already defined.
  * @param [in] $name
  */
  function is_defined($name) {
    global $macro_names;
    $size = count($macro_names);
    for ($i = 0; $i < $size; $i++) {
      if (strcmp($name, $macro_names[$i]) == false) {
        return $i;
      }
    }
    return -1;
  }

/**
  * Defines a new macro, checks for macro redefine conditions.
  */
  function define_macro() {
    
    global $macro_names;
    global $macros;

    $token = read_input(true);
    if ($token[1] != MACRO_NAME) {
      close(56);
    }
    else {
      $name = $token[0];
      $defined = is_defined($name);
      if ($defined > -1 ) {
        if (defined('DENY_REDEFINE') || $macros[$name]->static){
          if($macros[$name]->type != NULL_TYPE) {
            close(57);
          }
        }
        $macros[$name] = NULL;
        $macro = new macro();
      }
      else {
        $macro = new macro();
        $macro->name = $name;
        array_push($macro_names, $name);
      }
      $params = read_input(true);
      if ($params[1] != BLOCK) {
        close(56);
      }
      $body = read_input(true, true);
      if ($body[1] != BLOCK) {
        close(56);
      }
      if ($macro->type != NULL_TYPE) {
        $macro->get_param_names($params[0]);
        $macro->body = $body[0];
        $macro->type = USER;
        $macros[$name] = $macro;
      }
    }
  }

/**
  * Replaces one macro with another.
  */
  function let() {
    
    global $macros;
    global $macro_names;

    $token = read_input(true);
    if ($token[1] != MACRO_NAME) {
      close();
    }
    $defined = is_defined($token[0]);
    $defined_index = $defined;
    if ($defined == -1) {
      $replace = new macro();
      $replace->name = $token[0];
      $macros[$token[0]] = $replace;
      array_push($macro_names, $token[0]);
    }
    else {
      if (defined('DENY_REDEFINE') || $macros[$token[0]]->static) {
        close(57);
      }
      $replace = $macros[$token[0]];
    }
    $token = read_input(true);
    if ($token[1] != MACRO_NAME) {
      close(56);
    }
    $defined = is_defined($token[0]);
    if ($defined == -1) {
      close(56);
    }
    else {
      $with = $macros[$token[0]];
    }
    if( $replace->type != NULL_TYPE && $with->type != NULL_TYPE) {
      $replace->params = $with->params;
      $replace->body = $with->body;
      $replace->type = $with->type;
    }
    else if($with->type == NULL_TYPE && $replace->type != NULL_TYPE) {
      unset($macros[$replace->name]);
      unset($macro_names[$defined_index]);
      $macro_names = array_values($macro_names);
    }

  }

/**
  * Sets the "Ignore spaces" attribute
  */
  function set() {
    global $input_spaces;
    $token = read_input(true);
    if ($token[1] != BLOCK) {
      close(56);
    }
    if (!strcmp($token[0], "+INPUT_SPACES")) {
      $input_spaces = true;
    }
    else if (!strcmp($token[0], "-INPUT_SPACES")) {
      $input_spaces = false;
    }
    else {
      close(56);
    }
  }

/**
  * Macro class
  */
  class macro {

    public $name = ""; /*!< name of the macro*/
    public $body = ""; /*!< body of the macro to expand */
    public $params = array(); /*!< params array [0] - param name, [1] - param value */
    public $type = 0; /*!< macro type - see Global definitions */
    public $static = false; /*!< tells if the macro is static or not */
    
  /**
    * Function reads all macro's param names
    * @param $block [int] 
    */
    function get_param_names($block) {
      $matches = array();
      preg_match_all("#\\$[a-zA-Z_][\w]*#m", $block, $matches);
      foreach ($matches[0] as $match) {
        array_push($this->params, array($match));
        $block = str_replace($match, "", $block);
      }
      $block_len = strlen($block);
      if ($block_len > 0) {
        for ($i = 0; $i < $block_len; $i++) {
          if (!ctype_space($block[$i])) {
            close(55);
          }
        }
      }
    }

  /**
    * Reads the parameter value, when expanding a macro.
    * @param $param_index [in]
    */
    function get_param($param_index) {
      
      $token = read_input(true);
      
      if ($token[1] == END || $token[0] == NULL && $token[1] != BLOCK) {
        close(56);
      }
      $this->params[$param_index][1] = $token[0];
    }

  /**
    * Replaces every parameter in the macro body with it's value.
    */
    function fill_params() {
      
      $expanded_body = $this->body;
      
      foreach ($this->params as $param) {
        $expanded_body = preg_replace('#\\'.$param[0].'(@|\ |}|\n|\s|\W|$)#m', $param[1].'\\1', $expanded_body);
      }
      return $expanded_body;
    }
  
  /**
    * Expands the macro
    */
    function expand_macro() {

      global $macros;

      if ($this->type == DEF) {
        define_macro();
      }
      else if ($this->type == SET) {
        set();
      }
      else if ($this->type == LET) {
        let();  
      }
      else {
        if (count($this->params) == 0) {
          push_input($this->body);
        }
        else {
          $params_needed = count($this->params);
          for($i = 0; $i < $params_needed; $i++) {
            $this->get_param($i);
          }
          push_input($this->fill_params());
        }
      }
    }
  }

/**
  * Reads the entire block of text till the correct closing bracket.
  * @param [in] $return_escape
  */
  function read_block($return_escape) {
    $balance = 1;
    $block_body = "";
    
    while($balance) {
      $c = my_getchar();
      if ($c == '{') {
        $balance++;
        $block_body .= $c;
      }
      else if ($c == '}') {
        $balance--;
        if ($balance > 0) {
          $block_body .= $c;
        }
        else {
          return $block_body;
        }
      }
      else if ($c == '@') {
        $c = my_getchar();
        if ($return_escape) {
          $block_body .= '@';
        }
        switch ($c) {
          case '@':
            $block_body .= '@';
          break;
          case '{':
            $block_body .= '{';
          break;
          case '}':
            $block_body .= '}';
          break;
          default:
            if (!$return_escape) {
              $block_body .= '@';
            }
            $block_body .= $c;
          break;
        }
      }
      else {
        if (feof(INPUT)) {
          close(55);
        }
        $block_body .= $c;
      }
    }
    return $block_body;
  }

/**
  * Reads a macro name.
  */
  function read_name($c) {
    $name = '';
    while (ctype_alnum($c) || $c == '_') {
      $name .= $c;
      $c = my_getchar();
    }
    if($c) {
      push_input($c);
    }
    return '@'.$name;
  }

/**
  * The main finite automat that controls the flow.
  * @param [in] $return_all
  * @param [in] $return_escape
  */
  function read_input($return_all = false, $return_escape = false) {
    global $input_stack;
    global $input_spaces;
    while(1) {
      $c = my_getchar();
      switch ($c) {
        case '$':
        case '}':
          close(55);
        case '{':
          if ($return_all) {
            return array(read_block($return_escape), BLOCK);
          }
          else {
            fwrite(OUTPUT, read_block(false));
          }
        break;
        case '@':
          $c = my_getchar();
          if (ctype_alpha($c) || $c == '_') {
            return array(read_name($c), MACRO_NAME);
          }
          else if (feof(INPUT)) {
            close(55);
          }
          else {
            switch ($c) {
              case '@':
              case '$':
              case '{':
              case '}':
                if ($return_all) {
                  return array('@'.$c, ESCAPE);
                }
                else {
                  fwrite(OUTPUT, $c);
                }
              break;
              default:
                close(55);
              break;
            }
          }
        break;
        case "\r":
        break;
        default:
          if ($return_all) {
            if (!$input_spaces && (ctype_space($c) || ctype_cntrl($c))) {}
            else {
              return array($c, OTHER);
            }
          }
          else {
            if (!$input_spaces && (ctype_space($c) || ctype_cntrl($c))) {}
            else {
              fwrite(OUTPUT, $c);
            }
          }
        break;
      }
      if ($c == NULL && feof(INPUT) && count($input_stack) == 0) {
        return array("", END);
      }
    }
  }

/**
  * Main reading loop.
  */
  function read() {
    $token = read_input(false);
    while ($token[1] != END) {
      if ($token[1] == MACRO_NAME) {
        global $macros;
        if (is_defined($token[0]) == -1) {
          close(56);
        }
        else {
          $macros[$token[0]]->expand_macro();
        }
      }
      $token = read_input(false);
    }
  }
  
  process_script_params($argc, $argv); // process the params
  prepare_table(); // prepare the table
  read(); // and read
  close(0); // if all goes well, end with code 0
?>