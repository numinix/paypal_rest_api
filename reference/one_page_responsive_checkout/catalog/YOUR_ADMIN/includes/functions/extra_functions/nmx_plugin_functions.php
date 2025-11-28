<?php

if(!function_exists('nmx_zen_cfg_select_option')){

	function nmx_zen_cfg_select_option($select_array, $key_value, $key = '') {
	    $string = '';
		for ($i=0, $n=sizeof($select_array); $i<$n; $i++) {
			$name = ((zen_not_null($key)) ? 'configuration[' . $key . ']' : 'configuration_value');

			$string .= '<br><input type="radio" name="' . $name . '" value="' . $select_array[$i] . '"';

			if ($key_value == $select_array[$i]) $string .= ' CHECKED';

			$string .= ' id="' . strtolower($select_array[$i] . '-' . $name) . '"> ' . '<label for="' . strtolower($select_array[$i] . '-' . $name) . '" class="inputSelect">' . $select_array[$i] . '</label>';
		}

	    return $string;
	}
}

if(!function_exists('nmx_configuration_function')){
	function nmx_configuration_function ($set_function) {
		$zc156 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= 5.6));
		$substitute_function = array(
			'zen_cfg_select_option'
		);
		$first_bracket = strpos($set_function, '(');

		if($zc156 && in_array(substr($set_function,0,$first_bracket),$substitute_function)){
			return 'nmx_' . $set_function;
		} else {
			return $set_function;
		}
	}
}
