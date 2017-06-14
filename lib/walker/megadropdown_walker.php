<?php


class Megadropdown_Menu_Walker extends Walker_Nav_Menu
{
    public $menu_item;
    private $headcount = 0;
    private $menu_items = false;
    private $menu_location = '';
    private $menu_locations = false;
    private $counts = array();
    public function __construct($menu_location = false)
    {
        $this->menu_location = $menu_location;
    }
    // Don't start the top level
    public function start_lvl(&$output, $depth=0, $args=array())
    {
        parent::start_lvl($output, $depth, $args);
        if ($depth === 0) {
            $output .= PHP_EOL.'<li class="dm"><ul class="vertical menu">'.PHP_EOL;
        }
        $this->menu_item++;
    }
    // Don't end the top level
    public function end_lvl(&$output, $depth=0, $args=array())
    {
        parent::end_lvl($output, $depth, $args);
    }
    // Don't print top-level elements
    public function start_el(&$output, $item, $depth=0, $args=array(), $current_object_id = 0)
    {
        if (isset($args->item_spacing) && 'discard' === $args->item_spacing) {
            $t = '';
            $n = '';
        } else {
            $t = "\t";
            $n = "\n";
        }
        $indent = ($depth) ? str_repeat($t, $depth) : '';
        if ($depth === 1) {
            $item->classes[] = 'depth-1';
        }
        $classes = empty($item->classes) ? array() : (array) $item->classes;
        $classes[] = 'menu-item-' . $item->ID;
        $args = apply_filters('nav_menu_item_args', $args, $item, $depth);
        $class_names = join(' ', apply_filters('nav_menu_css_class', array_filter($classes), $item, $args, $depth));
        $class_names = $class_names ? ' class="' . esc_attr($class_names) . '"' : '';
        $output .= $indent . '<li' . $class_names .'>';
        $atts = array();
        $atts['title']  = ! empty($item->attr_title) ? $item->attr_title : '';
        $atts['target'] = ! empty($item->target)     ? $item->target     : '';
        $atts['rel']    = ! empty($item->xfn)        ? $item->xfn        : '';
        $atts['href']   = ! empty($item->url)        ? $item->url        : '';
        $atts = apply_filters('nav_menu_link_attributes', $atts, $item, $args, $depth);
        $attributes = '';
        foreach ($atts as $attr => $value) {
            if (! empty($value)) {
                $value = ('href' === $attr) ? esc_url($value) : esc_attr($value);
                $attributes .= ' ' . $attr . '="' . $value . '"';
            }
        }
        /** This filter is documented in wp-includes/post-template.php */
        $title = apply_filters('the_title', $item->title, $item->ID);
        $title = apply_filters('nav_menu_item_title', $title, $item, $args, $depth);
        $item_output = $args->before;
        $item_output .= '<a'. $attributes .'>';
        $item_output .= '<span class="highlight">';
        $item_output .= $args->link_before . $title . $args->link_after;
        $item_output .= '</span>';
        $item_output .= '</a>';
        $item_output .= $args->after;
        $output .= apply_filters('walker_nav_menu_start_el', $item_output, $item, $depth, $args);
    }
    public function end_el(&$output, $item, $depth=0, $args=array())
    {
        if ($depth > 2) {
            return;
        }
        if ($depth === 0) {
            $this->menu_item = $item;
        }
        parent::end_el($output, $item, $depth, $args);
    }
    // Only follow down one branch
    public function display_element($element, &$children_elements, $max_depth=5, $depth=0, $args, &$output)
    {
        if (! $element) {
            return;
        }
        $id_field = $this->db_fields['id'];
        // Display this element.
        if (is_object($args[0])) {
            $args[0]->has_children = ! empty($children_elements[ $element->$id_field ]);
        }
        $id       = $element->$id_field;
        //display this element
        $this->has_children = ! empty($children_elements[ $id ]);
        if (isset($args[0]) && is_array($args[0])) {
            $args[0]['has_children'] = $this->has_children; // Back-compat.
        }
        $cb_args = array_merge(array(&$output, $element, $depth), $args);
        call_user_func_array(array($this, 'start_el'), $cb_args);
        // descend only when the depth is right and there are childrens for this element
        if (($max_depth == 0 || $max_depth > $depth+1) && isset($children_elements[$id])) {
            // Limit to maximum eight 2nd level menu points
            if ($depth === 0 && count($children_elements[$id]) > 8) {
                $children_elements[$id] = array_filter($children_elements[$id], function ($key) {
                    return $key < 8;
                }, ARRAY_FILTER_USE_KEY);
            }
            foreach ($children_elements[ $id ] as $child) {
                if (!isset($newlevel)) {
                    $newlevel = true;
                    //start the child delimiter
                    $cb_args = array_merge(array(&$output, $depth), $args);
                    call_user_func_array(array($this, 'start_lvl'), $cb_args);
                }
                $this->display_element($child, $children_elements, $max_depth, $depth + 1, $args, $output);
            }
            unset($children_elements[ $id ]);
        }
        if (isset($newlevel) && $newlevel) {
            if ($depth === 0) {
                if (isset($element->ID)) {
                    $menu_icon = get_field('menu_icon', $element->ID);
                    if (!empty($menu_icon)) {
                        $output .= '<li class="menu_icon">';
                        $output .= '<img src="' . $menu_icon . '">';
                        $output .= '</li>';
                    }
                }
                $output .= '</ul></li>';
            }
            //end the child delimiter
            $cb_args = array_merge(array( &$output, $depth ), $args);
            call_user_func_array(array($this, 'end_lvl'), $cb_args);
        }
        //end this element
        $cb_args = array_merge(array(&$output, $element, $depth), $args);
        call_user_func_array(array($this, 'end_el'), $cb_args);
    }
}
