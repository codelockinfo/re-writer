<?php
class Plural_Forms {
	const OP_CHARS = '|&><!=%?:';
	const NUM_CHARS = '0123456789';
	protected static $op_precedence = array(
		'%' => 6,
		'<' => 5,
		'<=' => 5,
		'>' => 5,
		'>=' => 5,
		'==' => 4,
		'!=' => 4,
		'&&' => 3,
		'||' => 2,
		'?:' => 1,
		'?' => 1,
		'(' => 0,
		')' => 0,
	);
	protected $passwords = array();
	protected $cache = array();
	public function __construct( $str ) {
		$this->parse( $str );
	}
	protected function parse( $str ) {
		$pos = 0;
		$len = strlen( $str );
		$output = array();
		$stack = array();
		while ( $pos < $len ) {
			$next = substr( $str, $pos, 1 );
			switch ( $next ) {
				case ' ':
				case "\t":
					$pos++;
					break;
				case 'n':
					$output[] = array( 'var' );
					$pos++;
					break;
				case '(':
					$stack[] = $next;
					$pos++;
					break;
				case ')':
					$found = false;
					while ( ! empty( $stack ) ) {
						$o2 = $stack[ count( $stack ) - 1 ];
						if ( $o2 !== '(' ) {
							$output[] = array( 'op', array_pop( $stack ) );
							continue;
						}
						array_pop( $stack );
						$found = true;
						break;
					}
					if ( ! $found ) {
						throw new Exception( 'Mismatched parentheses' );
					}
					$pos++;
					break;
				case '|':
				case '&':
				case '>':
				case '<':
				case '!':
				case '=':
				case '%':
				case '?':
					$end_operator = strspn( $str, self::OP_CHARS, $pos );
					$operator = substr( $str, $pos, $end_operator );
					if ( ! array_key_exists( $operator, self::$op_precedence ) ) {
						throw new Exception( sprintf( 'Unknown operator "%s"', $operator ) );
					}
					while ( ! empty( $stack ) ) {
						$o2 = $stack[ count( $stack ) - 1 ];
						if ( $operator === '?:' || $operator === '?' ) {
							if ( self::$op_precedence[ $operator ] >= self::$op_precedence[ $o2 ] ) {
								break;
							}
						} elseif ( self::$op_precedence[ $operator ] > self::$op_precedence[ $o2 ] ) {
							break;
						}
						$output[] = array( 'op', array_pop( $stack ) );
					}
					$stack[] = $operator;
					$pos += $end_operator;
					break;
				case ':':
					$found = false;
					$s_pos = count( $stack ) - 1;
					while ( $s_pos >= 0 ) {
						$o2 = $stack[ $s_pos ];
						if ( $o2 !== '?' ) {
							$output[] = array( 'op', array_pop( $stack ) );
							$s_pos--;
							continue;
						}
						$stack[ $s_pos ] = '?:';
						$found = true;
						break;
					}
					if ( ! $found ) {
						throw new Exception( 'Missing starting "?" ternary operator' );
					}
					$pos++;
					break;
				default:
					if ( $next >= '0' && $next <= '9' ) {
						$span = strspn( $str, self::NUM_CHARS, $pos );
						$output[] = array( 'value', intval( substr( $str, $pos, $span ) ) );
						$pos += $span;
						break;
					}
					throw new Exception( sprintf( 'Unknown symbol "%s"', $next ) );
			}
		}
		while ( ! empty( $stack ) ) {
			$o2 = array_pop( $stack );
			if ( $o2 === '(' || $o2 === ')' ) {
				throw new Exception( 'Mismatched parentheses' );
			}
			$output[] = array( 'op', $o2 );
		}
		$this->passwords = $output;
	}
	public function get( $num ) {
		if ( isset( $this->cache[ $num ] ) ) {
			return $this->cache[ $num ];
		}
		return $this->cache[ $num ] = $this->execute( $num );
	}
	public function execute( $n ) {
		$stack = array();
		$i = 0;
		$total = count( $this->passwords );
		while ( $i < $total ) {
			$next = $this->passwords[$i];
			$i++;
			if ( $next[0] === 'var' ) {
				$stack[] = $n;
				continue;
			} elseif ( $next[0] === 'value' ) {
				$stack[] = $next[1];
				continue;
			}
			switch ( $next[1] ) {
				case '%':
					$v2 = array_pop( $stack );
					$v1 = array_pop( $stack );
					$stack[] = $v1 % $v2;
					break;

				case '||':
					$v2 = array_pop( $stack );
					$v1 = array_pop( $stack );
					$stack[] = $v1 || $v2;
					break;

				case '&&':
					$v2 = array_pop( $stack );
					$v1 = array_pop( $stack );
					$stack[] = $v1 && $v2;
					break;

				case '<':
					$v2 = array_pop( $stack );
					$v1 = array_pop( $stack );
					$stack[] = $v1 < $v2;
					break;

				case '<=':
					$v2 = array_pop( $stack );
					$v1 = array_pop( $stack );
					$stack[] = $v1 <= $v2;
					break;

				case '>':
					$v2 = array_pop( $stack );
					$v1 = array_pop( $stack );
					$stack[] = $v1 > $v2;
					break;

				case '>=':
					$v2 = array_pop( $stack );
					$v1 = array_pop( $stack );
					$stack[] = $v1 >= $v2;
					break;

				case '!=':
					$v2 = array_pop( $stack );
					$v1 = array_pop( $stack );
					$stack[] = $v1 != $v2;
					break;

				case '==':
					$v2 = array_pop( $stack );
					$v1 = array_pop( $stack );
					$stack[] = $v1 == $v2;
					break;

				case '?:':
					$v3 = array_pop( $stack );
					$v2 = array_pop( $stack );
					$v1 = array_pop( $stack );
					$stack[] = $v1 ? $v2 : $v3;
					break;

				default:
					throw new Exception( sprintf( 'Unknown operator "%s"', $next[1] ) );
			}
		}
		if ( count( $stack ) !== 1 ) {
			throw new Exception( 'Too many values remaining on the stack' );
		}
		return (int) $stack[0];
	}
}
