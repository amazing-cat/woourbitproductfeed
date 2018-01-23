<?php

if (!defined('URBIT_PRODUCT_FEED_PLUGIN_DIR')) {
    exit;
}

/**
 * Class UPF_Admin_Config_Page
 */
class UPF_Admin_Config_Page extends UPF_Admin_Page_Abstract
{
    /**
     * Page slug
     */
    const SLUG = 'product-feed';
    /**
     * Setup template
     */
    const BASE_TEMPLATE = 'admin/config_page';

    /**
     * @var array
     */
    protected $viewVars = array();

    /**
     * Init menu element
     */
    protected function init()
    {
        $this->menuElement = new UPF_Admin_Menu_Element(
            'Urbit Product Feed Settings',
            'Product Feed',
            'manage_options',
            static::SLUG
        );

        //init hooks
        add_action('admin_init', array($this, 'registerSettings'));
    }

    /**
     * Register settings
     */
    public function registerSettings()
    {
        $optionGroup = 'productfeed_group';

        // parameters: $option_group, $option_name, $sanitize_callback
        register_setting($optionGroup, UPF_Config::CONFIG_KEY);

        //add sections to view
        $this->viewVars['option_group'] = $optionGroup;
        $this->viewVars['sections'] = array();

        $this->initSectionCron();
        $this->initSectionFilter();
        $this->initSectionAttributes();
        $this->initSectionDimensions();
    }

    protected function initSectionCron()
    {
        $cacheSection = new UPF_Admin_Settings_Section('productfeed_cache', 'Feed Cache');

        $cacheSection->addField(new UPF_Admin_Settings_Field(
            'urbit_feed_cache_field',
            'Cache Duration (in hours)',
            $cacheSection->getPageId(),
            'admin/fields/input',
            array(
                'type'  => 'number',
                'name'  => UPF_Config::CONFIG_KEY . '[cron][cache_duration]',
                'value' => esc_attr($this->getConfig("cron/cache_duration", 1)),
            )
        ));

        $cacheSection->registerSection();
        $this->viewVars['sections'][] = $cacheSection;
    }

    protected function initSectionFilter()
    {
        $filterSection = new UPF_Admin_Settings_Section('productfeed_filter', 'Product Filter');

        $filterSection->addField(new UPF_Admin_Settings_Field(
            'urbit_product_filter_countries_field',
            'Feed Country',
            $filterSection->getPageId(),
            'admin/fields/select',
            array(
                'name'     => UPF_Config::CONFIG_KEY . '[filter][countries][]',
                'size'     => count($this->getCountries()),
                'elements' => array_merge(array(
                    0 => array(
                        'value' => 0,
                        'param' => '',
                        'text'  => 'Default',
                    ),
                ), $this->getCountries()),
            )
        ));

        $filterSection->addField(new UPF_Admin_Settings_Field(
            'urbit_product_filter_categories_field',
            'Categories',
            $filterSection->getPageId(),
            'admin/fields/multiselect',
            array(
                'name'     => UPF_Config::CONFIG_KEY . '[filter][categories][]',
                'class'    => 'collects-config',
                'size'     => count($this->getCategoriesWithSelected()),
                'elements' => $this->getCategoriesWithSelected(),
            )
        ));

        $filterSection->addField(new UPF_Admin_Settings_Field(
            'urbit_product_filter_tags_field',
            'Tags',
            $filterSection->getPageId(),
            'admin/fields/multiselect',
            array(
                'name'     => UPF_Config::CONFIG_KEY . '[filter][tags][]',
                'class'    => 'tags-config',
                'size'     => count($this->getTagsWithSelected()),
                'elements' => $this->getTagsWithSelected(),
            )
        ));

        $filterSection->addField(new UPF_Admin_Settings_Field(
            'urbit_product_filter_product_field',
            'Product ID',
            $filterSection->getPageId(),
            'admin/fields/fourth_filter',
            array(
                'name'     => UPF_Config::CONFIG_KEY . '[filter][product][]',
                'elements' => $this->getProducts(),
            )
        ));

        $filterSection->registerSection();
        $this->viewVars['sections'][] = $filterSection;
    }

    protected function initSectionAttributes()
    {
        $attributesSection = new UPF_Admin_Settings_Section('productfeed_attributes', 'Product Attributes');

        $product_ids = $this->getAllProducts();

        $attrNames = array(
            'size',
            'sizeType',
            'sizeSystem',
            'color',
            'gender',
            'material',
            'pattern',
            'age group',
            'condition',
        );

        foreach ($attrNames as $name) {
            $key = str_replace(" ", "_", $name);
            $name = $this->splitAtUpperCase($name);

            $attributesSection->addField(new UPF_Admin_Settings_Field(
                "urbit_product_attribute_{$key}_field",
                ucfirst($name),
                $attributesSection->getPageId(),
                'admin/fields/select',
                array(
                    'name'     => UPF_Config::CONFIG_KEY . "[attributes][{$key}]",
                    'elements' => array_merge(
                        array(
                            '' => array(
                                'value' => '',
                                'text'  => '------None------',
                            ),
                        ),
                        $this->getCalculatedAttributes($key),
                        $this->getDbAttributes($product_ids, $key),
                        $this->getAttributes($key)
                    ),
                    'value'    => $this->getConfig("attributes/{$key}"),
                )
            ));
        }

        $attributesSection->addField(new UPF_Admin_Settings_Field(
            'urbit_product_additional_fields_field',
            'Additional attributes',
            $attributesSection->getPageId(),
            'admin/fields/multiselect',
            array(
                'name'     => UPF_Config::CONFIG_KEY . '[attributes][additional][]',
                'class'    => 'addattr-config',
                'elements' => $this->selectedAdditionAttributes($this->getAttributes('')),
                'size'     => '10',
            )
        ));

        $attributesSection->registerSection();
        $this->viewVars['sections'][] = $attributesSection;
    }

    protected function initSectionDimensions()
    {
        $dimensionsSection = new UPF_Admin_Settings_Section('productfeed_dimensions', 'Product Dimensions');

        $product_ids = $this->getAllProducts();

        $fields = array(
            'name',
            'description',
            'id',
            'gtin',
            'mpn',
            'heightValue',
            'heightUnit',
            'lengthValue',
            'lengthUnit',
            'widthValue',
            'widthUnit',
            'weightValue',
            'weightUnit',
        );

        foreach ($fields as $name) {
            $key = str_replace(" ", "_", $name);
            $name = $this->splitAtUpperCase($name);

            $dimensionsSection->addField(new UPF_Admin_Settings_Field(
                "urbit_product_attributes_{$key}_field",
                ucfirst($name),
                $dimensionsSection->getPageId(),
                'admin/fields/select',
                array(
                    'name'     => UPF_Config::CONFIG_KEY . "[attributes][{$key}]",
                    'elements' => array_merge(
                        array(
                            '' => array(
                                'value' => '',
                                'text'  => '------None------',
                            ),
                        ),
                        $this->getCalculatedAttributes($key),
                        $this->getDbAttributes($product_ids, $key),
                        $this->getAttributes($key)
                    ),
                    'value'    => $this->getConfig("attributes/{$key}"),
                )
            ));
        }

        $dimensionsSection->registerSection();
        $this->viewVars['sections'][] = $dimensionsSection;
    }

    /**
     * @return array
     */
    protected function getCountries()
    {
        $countries_obj = new WC_Countries();
        $countries = $countries_obj->get_countries();

        $selectedCountries = $this->core->getConfig()->getSelect("filter/countries", array());

        $result = array();

        foreach ($countries as $code => $country) {
            $tax = WC_Tax::find_rates(array(
                'country' => $code,
            ));

            if (!empty($tax)) {
                $param = '';

                if (!empty($selectedCountries)) {
                    $param = in_array($code, $selectedCountries) ? 'selected="selected"' : '';
                }

                $lastTax = array_pop($tax);

                $result[] = array(
                    'value' => $code,
                    'param' => $param,
                    'text'  => $country . ' - ' . number_format($lastTax['rate'], 2, '.', '') . '%',
                );
            }
        }

        return $result;
    }

    /**
     * @return array
     * @throws Exception
     */
    protected function getProducts()
    {
        $pf = new WC_Product_Factory();

        $selectedProducts = $this->core->getConfig()->getSelect("filter/product", array());
        $selected = array();

        foreach ($selectedProducts as $post) {
            array_push($selected, $pf->get_product($post));
        }

        $selectedTags = $this->core->getConfig()->getSelect("filter/tags", array());
        $selectedCategories = $this->core->getConfig()->getSelect("filter/categories", array());
        $stock = esc_attr($this->getConfig("filter/stock"));

        $query_result = $this->core->getQuery()->productsQuery(array(
            'categories' => $selectedCategories,
            'tags' => $selectedTags,
            'stock' => $stock,
        ));

        $product_posts = $query_result->get_posts();
        $products = array();

        foreach ($product_posts as $post) {
            $temp = $pf->get_product($post);

            if (!in_array((string) $temp->get_id(), $selectedProducts)) {
                array_push($products, $temp);
            }
        }

        $result = array(
            'products' => $products,
            'selected' => $selected,
        );

        return $result;
    }

    /**
     * define selected items
     * @param  array $attributes
     * @return array
     */
    protected function selectedAdditionAttributes($attributes)
    {
        $selectedAttributes = $this->core->getConfig()->getSelect("attributes/additional", array());

        $result = array();

        foreach ($attributes as $attribute) {
            $result[] = array(
                'value' => $attribute['text'],
                'param' => !empty($selectedAttributes) && in_array($attribute['text'], $selectedAttributes) ? 'selected="selected"' : '',
                'text'  => $attribute['text'],
            );
        }

        return $result;
    }

    /**
     * explode string based on upper-case characters
     * @param  string $s
     * @return string
     */
    protected function splitAtUpperCase($s)
    {
        $name = preg_split('/(?=[A-Z])/', $s, -1, PREG_SPLIT_NO_EMPTY);

        if (!isset($name[0])) {
            $name[0] = '';
        }

        if (!isset($name[1])) {
            $name[1] = '';
        }

        return trim($name[0] . ' ' . $name[1]);
    }

    /**
     * @return array
     */
    protected function getCategoriesWithSelected()
    {
        $result = array();
        $categories = get_categories(array(
            'taxonomy' => 'product_cat',
        ));

        $selectedCategories = $this->core->getConfig()->getSelect("filter/categories", array());

        foreach ($categories as $category) {
            $param = '';

            if (!empty($selectedCategories)) {
                $param = in_array($category->term_id, $selectedCategories) ? 'selected="selected"' : '';
            }

            $result[] = array(
                'value' => $category->term_id,
                'param' => $param,
                'text'  => $category->cat_name,
            );
        }

        return $result;
    }

    /**
     * @return array
     */
    protected function getTagsWithSelected()
    {
        $result = array();
        $tags = get_terms(array(
            'taxonomy' => 'product_tag',
        ));

        $selectedTags = $this->core->getConfig()->getSelect("filter/tags", array());

        foreach ($tags as $tag) {
            $param = '';

            if (!empty($selectedTags)) {
                $param = in_array($tag->term_id, $selectedTags) ? 'selected="selected"' : '';
            }

            $result[] = array(
                'value' => $tag->term_id,
                'param' => $param,
                'text'  => $tag->name,
            );
        }

        return $result;
    }

    /**
     * @param $key
     * @return array
     */
    public function getAttributes($key)
    {
        $result = array(
            array(
                'value' => 'attr_empty',
                'text' => '------ Features & Attributes ------',
            ),
        );

        $taxonomies = wc_get_attribute_taxonomies();

        foreach ($taxonomies as $tax) {
            $param = '';
            $selected = $this->core->getConfig()->getSelect("attributes/{$key}" , array());

            if (!empty($selected)) {
                $param = in_array($tax->attribute_name, $selected) ? 'selected="selected"' : '';
            }

            $result[] = array(
                'value' => $tax->attribute_name,
                'param' => $param,
                'text'  => $tax->attribute_label,
            );
        }

        return $result;
    }

    /**
     * @return array
     */
    protected function getAllProducts()
    {
        $type = 'product';
        $number = wp_count_posts($type);
        $p_posts = get_posts(array(
            'post_type' => $type,
            'posts_per_page' => $number->publish,
        ));

        $ids_array = array();

        foreach ($p_posts as $post) {
            $ids_array[] = $post->ID;
        }

        return $ids_array;
    }

    /**
     * @param $field_key
     * @return array
     */
    protected function getCalculatedAttributes($field_key)
    {
        $exceptions = array(
            'category_ids',
            'tag_ids',
            'gallery_image_ids',
            'downloads',
            'rating_counts',
            'default_attributes',
            'attributes',
            'upsell_ids',
            'cross_sell_ids',
            'dimensions',
            'date_on_sale_from',
            'date_on_sale_to',
            'date_created',
            'date_modified',
            'children',
            'file',
            'tags',
            'gallery_attachment_ids',
            'files',
            'matching_variation',
            'cross_sells',
            'upsells',
            'post_data',
            'child',
            'related_terms',
            'related',
            'variation_default_attributes',
        );

        $products_fields = array(
            'calc_empty' => array(
                'value' => 'calc.empty',
                'text' => '------ Calculated ------',
            ),
        );

        $methods = get_class_methods('WC_Product');

        foreach ($methods as $method) {
            if (strpos($method, 'get_') !== false) {
                $method_name = str_replace('get_', '', $method);

                if (!in_array($method_name, $products_fields) && !in_array($method_name, $exceptions)) {
                    $selected = $this->core->getConfig()->getSelect("attributes/{$field_key}" , array());

                    $selectKey = "calc.{$method_name}";

                    $products_fields[$method_name] = array(
                        'value' => $selectKey,
                        'param' => !empty($selected) && $selectKey === $selected[0] ? 'selected="selected"' : '',
                        'text' => str_replace('_', ' ', ucfirst($method_name)),
                    );
                }
            }
        }

        return $products_fields;
    }

    /**
     * @param $product_ids
     * @param $field_key
     * @return array
     */
    protected function getDbAttributes($product_ids, $field_key)
    {
        $exceptions = array(
            '_product_attributes',
        );

        $post_meta_keys = array(
            'db_empty' => array(
                'value' => 'db.empty',
                'text' => '------ Db Fields ------',
            ),
        );

        foreach ($product_ids as $id) {
            $m_post = get_post_meta($id);
            $keys = array_keys($m_post);

            foreach ($keys as $key) {
                if (!in_array($key, $post_meta_keys) && !in_array($key, $exceptions)) {
                    $selected = $this->core->getConfig()->getSelect("attributes/{$field_key}", array());

                    $selectKey = "db.{$key}";

                    $post_meta_keys[$key] = array(
                        'value' => $selectKey,
                        'param' => !empty($selected) && $selectKey === $selected[0] ? 'selected="selected"' : '',
                        'text' => $key,
                    );
                }
            }
        }

	$keys = ['_dimension_unit', '_weight_unit'];

	foreach ($keys as $key) {
                if (!in_array($key, $post_meta_keys) && !in_array($key, $exceptions)) {
                    $selected = $this->core->getConfig()->getSelect("attributes/{$field_key}", array());

                    $selectKey = "db.{$key}";

                    $post_meta_keys[$key] = array(
                        'value' => $selectKey,
                        'param' => !empty($selected) && $selectKey === $selected[0] ? 'selected="selected"' : '',
                        'text' => $key,
                    );
                }
        }

        return $post_meta_keys;
    }

    /**
     * Override parent class function
     * Add view vars to print function
     *
     * @param array $vars
     * @param string|null $template
     */
    public function printTemplate($vars = array(), $template = null)
    {
        $vars = array_merge((array)$vars, $this->viewVars);

        parent::printTemplate($vars, $template);
    }

    /**
     * Helper function
     * Get config param
     * @param string $name
     * @return mixed
     * @throws Exception
     */
    protected function getConfig($name)
    {
        return $this->core->getConfig()->get($name, '');
    }
}
