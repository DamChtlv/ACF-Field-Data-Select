<?php
defined('ABSPATH') || exit;

if (!class_exists('acf_field_field_data_select')) {
    class acf_field_field_data_select extends acf_field {

        function initialize() {
            $this->name = 'field_data_select';
            $this->label = _x('Field Data Select', 'noun', 'acf');
            $this->category = 'choice';
            $this->defaults = array(

                /** Clone field */
                'clone' 		=> '',

                /** Select field */
                'multiple' 		=> 0,
                'allow_null' 	=> 0,
                'choices'		=> array(),
                'default_value'	=> '',
                'ui'			=> 0,
                'ajax'			=> 1,
                'ajax_action'   => 'acf/fields/clone/query',
                'placeholder'	=> '',
                'return_format'	=> 'value',

                /** Repeater field */
                'sub_fields'	=> array(),
                'layout'		=> 'table',

                /** Flexible content field */
                'layouts'		=> array(),
            );

            /** Clone field */
            $this->cloning = array();
            $this->have_rows = 'single';
            acf_enable_filter('clone');
        }

        function get_clone_setting_choices( $value ) {

            // vars
            $choices = array();

            // bail early if no $value
            if( empty($value) ) return $choices;

            // force value to array
            $value = acf_get_array( $value );

            // loop
            foreach( $value as $v ) {
                $choices[ $v ] = $this->get_clone_setting_choice( $v );
            }

            // return
            return $choices;

        }

        function get_clone_setting_choice( $selector = '' ) {

            // bail early no selector
            if( !$selector ) return '';

            // ajax_fields
            if( isset($_POST['fields'][ $selector ]) ) {
                return $this->get_clone_setting_field_choice( $_POST['fields'][ $selector ] );
            }

            // field
            if( acf_is_field_key($selector) ) {
                return $this->get_clone_setting_field_choice( acf_get_field($selector) );
            }

            // group
            if( acf_is_field_group_key($selector) ) {
                return $this->get_clone_setting_group_choice( acf_get_field_group($selector) );
            }


            // return
            return $selector;

        }


        function get_clone_setting_field_choice( $field ) {

            if( !$field ) return __('Unknown field', 'acf');

            // title
            $title = $field['label'] ? $field['label'] : __('(no title)', 'acf');

            // append type
            $title .= ' (' . $field['type'] . ')';

            // ancestors
            // - allow for AJAX to send through ancestors count
            $ancestors = isset($field['ancestors']) ? $field['ancestors'] : count(acf_get_field_ancestors($field));
            $title = str_repeat('- ', $ancestors) . $title;

            // return
            return $title;

        }


        function get_clone_setting_group_choice( $field_group ) {

            if( !$field_group ) return __('Unknown field group', 'acf');

            // return
            return sprintf( __('All fields from %s field group', 'acf'), $field_group['title'] );

        }


        function get_valid_layout($layout = array()) {

            $layout = wp_parse_args($layout, array(
                'key'			=> uniqid('layout_'),
                'name'			=> '',
                'label'			=> '',
                'display'		=> 'block',
                'sub_fields'	=> array(),
                'min'			=> '',
                'max'			=> '',
            ));

            // return
            return $layout;
        }


        function render_field( $field ) {

            // convert
            $value = acf_get_array($field['value']);
            $choices = acf_get_array($field['choices']);

            // placeholder
            if (empty($field['placeholder']))
                $field['placeholder'] = _x('Field Data Select', 'verb', 'acf');

            // add empty value (allows '' to be selected)
            if (empty($value))
                $value = array('');

            // prepend empty choice
            // - only for single selects
            // - have tried array_merge but this causes keys to re-index if is numeric (post ID's)
            if ($field['allow_null'] && !$field['multiple'])
                $choices = array('' => "- {$field['placeholder']} -") + $choices;

            // clean up choices if using ajax
            if ($field['ui'] && $field['ajax']) {
                $minimal = array();
                foreach ($value as $key)
                    if (isset($choices[$key]))
                        $minimal[$key] = $choices[$key];

                $choices = $minimal;
            }

            // vars
            $select = array(
                'id'				=> $field['id'],
                'class'				=> $field['class'],
                'name'				=> $field['name'],
                'data-placeholder'	=> $field['placeholder'],
                'data-allow_null'	=> $field['allow_null']
            );

            // special atts
            if( !empty($field['readonly']) ) $select['readonly'] = 'readonly';
            if( !empty($field['disabled']) ) $select['disabled'] = 'disabled';
            if( !empty($field['ajax_action']) ) $select['data-ajax_action'] = $field['ajax_action'];

            // append
            $select['value']        = $value;
            $select['choices']      = $choices;

            /**
             *  If no clone is found, return standard select
             */
            if (empty($field['clone']) || !acf_is_field_key($field['clone'][0]))
                return acf_select_input($select);

            /**
             *  Default mode: Just use field settings as source for "choices"
             */
            if (empty($field['source'])) {

                $target_field               = acf_get_field($field['clone'][0]);
                $target_field_name          = $target_field['name'];

                /**
                 *  Field: Select / Checkbox / Radio
                 */
                if ($target_field['type'] == 'select' || $target_field['type'] == 'checkbox' || $target_field['type'] == 'radio')
                    $choices   = $target_field['choices'];

                /**
                 *  Field: Repeater
                 */
                elseif ($target_field['type'] == 'repeater') {

                    if (!empty($target_sub_fields = $target_field['sub_fields']))
                        foreach ($target_sub_fields as $target_sub_field)
                            $choices[$target_sub_field['label']] = $target_sub_field['name'];

                }

                /**
                 *  Field: Flexible Content
                 */
                elseif ($target_field['type'] == 'flexible_content') {

                    // vars
                    $sub_fields = acf_get_fields($target_field);
                    $layouts    = array();

                    // loop through layouts, sub fields and swap out the field key with the real field
                    foreach (array_keys($target_field['layouts']) as $i) {

                        // extract layout
                        $layout = acf_extract_var($target_field['layouts'], $i);
                        $layout = $this->get_valid_layout($layout);

                        // append sub fields
                        if (!empty($sub_fields)) {
                            foreach (array_keys($sub_fields) as $k) {

                                // check if 'parent_layout' is empty
                                // parent_layout did not save for this field, default it to first layout
                                if (empty($sub_fields[$k]['parent_layout']))
                                    $sub_fields[$k]['parent_layout'] = $layout['key'];

                                // append sub field to layout,
                                if ($sub_fields[$k]['parent_layout'] == $layout['key'])
                                    $layout['sub_fields'][] = acf_extract_var($sub_fields, $k);
                            }
                        }

                        // append back to layouts
                        $field['layouts'][$i]   = $layout;
                        $layouts                = array_merge($layouts, $field['layouts']);
                    }

                    foreach ($layouts as $layout_id => $layout) {

                        $layout = $layouts[$i];

                        /** Add layout key */
                        $choices[$layout['key']] = $layout['key'];

                        /** Add layout name */
                        $choices[$layout['name']] = $layout['label'];

                        /** Add subfields */
                        if (!empty($layout['sub_fields']))
                            foreach ($layout['sub_fields'] as $sub_field)
                                $choices[$sub_field['name']] = $sub_field['label'];

                    }
                }

                else {

                    $choices = array(
                        $target_field['name'] => $target_field['label']
                    );
                }

            /**
             *  Advanced mode: Use proxy choices from post_id "source"
             */
            } else {

                $field_destination          = $field['source'];
                $target_field               = get_field_object($field['clone'][0], $field_destination);
                $target_field_name          = $target_field['name'];
                $target_field_value         = $target_field['value'];

                if (empty($target_field['value']))
                    return acf_select_input($select);

                /**
                 *  Field: Select / Checkbox / Radio
                 */
                if ($target_field['type'] == 'select' || $target_field['type'] == 'checkbox' || $target_field['type'] == 'radio') {
                    $target_field_choices           = $target_field['choices'];
                    $choices                        = $target_field_choices;
                    $target_field_value             = is_array($target_field_value) ? reset($target_field_value) : $target_field_value;
                    $choices[$target_field_value]   = $target_field_value;
                }

                /**
                 *  Field: Repeater
                 */
                elseif ($target_field['type'] == 'repeater') {

                    $target_value_has_multiple_values = is_array($target_field_value) && !empty($target_field_value) && count($target_field_value) > 1;

                    if (!$target_value_has_multiple_values)
                        $choices[$target_field_value]   = $target_field_value;
                    else
                        foreach ($target_field_value as $index => $target_field_sub_value)
                            $choices['Repeater child ' . $index] = array_flip($target_field_sub_value);

                }

                /**
                 *  Field: Flexible Content
                 */
                elseif ($target_field['type'] == 'flexible_content') {

                    $target_value_has_multiple_values = is_array($target_field_value) && !empty($target_field_value) && count($target_field_value) > 1;

                    /** Character(s) to separate values we're going to get back with an explode on the string in load_value */
                    $value_sep = '--';

                    if (!$target_value_has_multiple_values) {

                        $index = 0;
                        $target_field_flexible_value = $target_field_value[$index];
                        $choice_value = $target_field_name . $value_sep . $index . $value_sep . $field_destination;

                        $target_flexible_title = $target_field_flexible_value['acfe_flexible_layout_title'] ?? $target_field_flexible_value['acf_fc_layout'];
                        $choices[$choice_value] = $target_flexible_title;

                    } else {

                        foreach ($target_field_value as $index => $target_field_sub_value) {

                            $choice_value = $target_field_name . $value_sep . $index . $value_sep . $field_destination;

                            $target_flexible_title = $target_field_sub_value['acfe_flexible_layout_title'] ?? $target_field_sub_value['acf_fc_layout'];
                            $choices[$choice_value] = $target_flexible_title;

                        }
                    }

                }

            }

            // append
            $select['value'] = $value;
            $select['choices'] = $choices;

            // render
            acf_select_input($select);

        }


        function render_field_settings($field) {

            // encode choices (convert from array)
            $field['choices']       = acf_encode_choices($field['choices']);
            $field['default_value'] = acf_encode_choices($field['default_value'], false);

            // temp enable 'local' to allow .json fields to be displayed
            acf_enable_filter('local');

            // choices
            acf_render_field_setting( $field, array(
                'label'			=> __('Target Field', 'acf'),
                'instructions'	=> __('Select one field type from field group you wish to get data from (select, repeater, flexible_content...)', 'acf'),
                'type'			=> 'select',
                'name'			=> 'clone',
                'multiple' 		=> 0,
                'allow_null' 	=> 1,
                'choices'		=> $this->get_clone_setting_choices($field['clone']),
                'ui'			=> 1,
                'ajax'			=> 1,
                'ajax_action'	=> $this->defaults['ajax_action'],
                'placeholder'	=> '',
            ));

            acf_disable_filter('local');

            // source
            acf_render_field_setting( $field, array(
                'label'			=> __('Target Source', 'acf'),
                'instructions'	=> __('Where we should grab the data from', 'acf'),
                'name'			=> 'source',
                'type'			=> 'text',
                'placeholder'	=> 'post_id (ex: 12)',
            ));

        }


        function load_value( $value, $post_id, $field ) {

            // ACF4 null
            if( $value === 'null' ) return false;

            /** Admin value */
            if (is_admin() && !wp_doing_ajax())
                return $value;

            if (is_array($value))
                return $value;

            /** Front value */
            $value_sep = '--';
            $value_array = explode($value_sep, $value);
            if (empty($value_array) || count($value_array) <= 1)
                return $value;

            $selector = $value_array[0];
            $position = $value_array[1];

            /** Can't trust explode for post_id because it can contains the separator character and make a false post_id */
            $post_id = substr($value, strlen($selector) + strlen($position) + (strlen($value_sep) * 2));

            $target_field = get_field($selector, $post_id);
            if (empty($target_field) || !is_array($target_field))
                return $value;

            /** Return our selected layout only */
            $value = array($target_field[$position]);

            return $value;
        }


        function update_field( $field ) {

            // decode choices (convert to array)
            $field['choices'] = acf_decode_choices($field['choices']);
            $field['default_value'] = acf_decode_choices($field['default_value'], true);

            // return
            return $field;
        }


        function update_value( $value, $post_id, $field ) {

            // Bail early if no value.
            if( empty($value) ) {
                return $value;
            }

            // Format array of values.
            // - Parse each value as string for SQL LIKE queries.
            if( is_array($value) ) {
                $value = array_map('strval', $value);
            }

            // return
            return $value;
        }


        function translate_field( $field ) {

            // translate
            $field['choices'] = acf_translate( $field['choices'] );

            // return
            return $field;

        }

    }
}

new acf_field_field_data_select();
