<?php

if (!function_exists('nmx_create_defines')) {
    function nmx_create_defines($defines = array())
    {
        if (is_array($defines) && count($defines) > 0) {
            foreach ($defines as $constantName => $constantValue) {
                if (!defined($constantName)) {
                    define($constantName, $constantValue);
                }
            }
        }
    }
}

if (!function_exists('nmx_check_field')) {
    function nmx_check_field($tableName, $columnName)
    {
        global $db;
        //Getting table fields through mysql built in function, passing db name and table name
        $tableFields = $db->metaColumns($tableName);

        $columnName = strtoupper($columnName);
        //loop to traverse tableFields result set
        foreach ($tableFields as $key => $value) {
            if ($key == $columnName) {
                return true;
            }
        }
        //end of loop
        return false;

    } //end of function
}


if (!function_exists('nmx_zen_cfg_select_option')) {

    function nmx_zen_cfg_select_option($select_array, $key_value, $key = '')
    {
        $string = '';
        for ($i = 0, $n = sizeof($select_array); $i < $n; $i++) {
            $name = ((zen_not_null($key)) ? 'configuration[' . $key . ']' : 'configuration_value');

            $string .= '<br><input type="radio" name="' . $name . '" value="' . $select_array[$i] . '"';

            if ($key_value == $select_array[$i])
                $string .= ' CHECKED';

            $string .= ' id="' . strtolower($select_array[$i] . '-' . $name) . '"> ' . '<label for="' . strtolower($select_array[$i] . '-' . $name) . '" class="inputSelect">' . $select_array[$i] . '</label>';
        }

        return $string;
    }
}

if (!function_exists('nmx_configuration_function')) {
    function nmx_configuration_function($set_function)
    {
        $zc156 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= 5.6));
        $substitute_function = array(
            'zen_cfg_select_option'
        );
        $first_bracket = strpos($set_function, '(');

        if ($zc156 && in_array(substr($set_function, 0, $first_bracket), $substitute_function)) {
            return 'nmx_' . $set_function;
        } else {
            return $set_function;
        }
    }
}

if(!function_exists('zen_cfg_password_input_field')) {
    function zen_cfg_password_input_field($text, $key = '') {
        return zen_draw_password_field($key, $text);
    }
}