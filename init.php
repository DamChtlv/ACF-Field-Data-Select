<?php
defined('ABSPATH') || exit;

if (!class_exists('acf_field_field_data_select')) :
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

             /** Character(s) to separate values we're going to get back with an explode on the string in load_value */
            $this->value_sep = '--';

            /** Clone field */
            $this->cloning = array();
            $this->have_rows = 'single';
            acf_enable_filter('clone');
        }


        function render_field( $field ) {

            // convert
            $value = acf_get_array($field['value']);
            $choices = acf_get_array($field['choices']);

            // placeholder
            if (empty($field['placeholder']))
                $field['placeholder'] = _x('Field Data Select', 'verb', 'acf');

            // _print_r($choices);
            // _print_r($value);
            _print_r($field['clone']);

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
            if (empty($field['clone']) || !acf_is_field_key($field['clone']))
                return acf_select_input($select);

            /**
             *  Default mode: Just use field settings as source for "choices"
             */
            if (empty($field['source'])) {

                $target_field               = acf_get_field($field['clone']);
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
                    $field_flexible = acf_get_field_type('flexible_content');
                    $sub_fields = acf_get_fields($target_field);
                    $layouts    = array();

                    // loop through layouts, sub fields and swap out the field key with the real field
                    foreach (array_keys($target_field['layouts']) as $i) {

                        // extract layout
                        $layout = acf_extract_var($target_field['layouts'], $i);
                        $layout = $field_flexible->get_valid_layout($layout);

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

                /**
                 *  Fallback value
                 */
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
                $target_field               = get_field_object($field['clone'], $field_destination);
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

                    if (!$target_value_has_multiple_values) {

                        $index = 0;
                        $target_field_flexible_value = $target_field_value[$index];
                        $choice_value = $target_field_name . $this->value_sep . $index . $this->value_sep . $field_destination;

                        $target_flexible_title = $target_field_flexible_value['acfe_flexible_layout_title'] ?? $target_field_flexible_value['acf_fc_layout'];
                        $choices[$choice_value] = $target_flexible_title;

                    } else {

                        foreach ($target_field_value as $index => $target_field_sub_value) {

                            $choice_value = $target_field_name . $this->value_sep . $index . $this->value_sep . $field_destination;

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

            $clone_field_type = acf_get_field_type('clone');

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
                'choices'		=> $clone_field_type->get_clone_setting_choices($field['clone']),
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


        function decode_selector($value) {

            if (empty($value))
                return;

            $value_array = explode($this->value_sep, $value);
            if (empty($value_array) || count($value_array) <= 1)
                return;

            $selector = $value_array[0];
            return $selector;

        }

        function decode_position($value) {

            if (empty($value))
                return;

            $value_array = explode($this->value_sep, $value);
            if (empty($value_array) || count($value_array) <= 1)
                return;

            $position = $value_array[1];
            return $position;

        }

        function decode_post_id($value, $selector, $position) {

            if (empty($value) || empty($selector) || empty($position))
                return;

            /** Can't trust explode for post_id because it can contains the separator character and make a false post_id */
            $post_id = substr($value, strlen($selector) + strlen($position) + (strlen($this->value_sep) * 2));
            return $post_id;

        }


        /**
         * Decode string value into array
         *
         * @param [string] $value
         * @return array
         */
        function decode_value($value) {

            if (empty($value))
                return array();

            $selector   = $this->decode_selector($value);
            $position   = $this->decode_position($value);
            $post_id    = $this->decode_post_id($value, $selector, $position);

            return array(
                'selector'  => $selector,
                'position'  => $position,
                'post_id'   => $post_id,
            );
        }


        function load_value( $value, $post_id, $field ) {

            // ACF4 null
            if( $value === 'null' ) return false;

            /** Admin value */
            if (is_admin() && !wp_doing_ajax())
                return $value;

            if (is_array($value))
                return $value;

            /** Decode string value into array */
            $decoded_value = $this->decode_value($value);
            if (empty($decoded_value))
                return $value;

            $selector   = $decoded_value['selector'];
            $position   = $decoded_value['position'];
            $post_id    = $decoded_value['post_id'];

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

    acf_register_field_type('acf_field_field_data_select');

endif;
