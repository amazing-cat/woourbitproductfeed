<?php

/**
 * Class UPF_Product
 * Working and process with Woocommerce Product
 *
 * Magic properties
 * @property boolean $isSimple
 * @property boolean $isVariable
 * @property boolean $isVariation
 * @property boolean $isProcessable
 *
 * Field properties (for feed $data property):
 * @property string $id
 * @property string $name
 * @property string $description
 * @property string $link
 * @property array $prices
 * @property array $brands
 * @property array $attributes
 * @property string $gtin
 * @property array $categories
 * @property string $image_link
 * @property array $additional_image_links
 * @property string $item_group_id
 * @property array $dimensions
 *
 * @property string $sizeType
 * @property string $size
 * @property string $color
 * @property string $gender
 * @property string $material
 * @property string $pattern
 * @property string $age_group
 * @property string $condition
 */
class UPF_Product
{
    /**
     * @var UPF_Core
     */
    protected $core;

    /**
     * @var WC_Product
     */
    protected $product;

    /**
     * @var array
     */
    protected $data = array();

    /**
     * UPF_Product constructor.
     * @param UPF_Core $core
     * @param WC_Product|null $product
     */
    public function __construct(UPF_Core $core, WC_Product $product = null)
    {
        $this->core = $core;
        $this->product = $product;
    }

    /**
     * @return bool
     */
    public function isVariable()
    {
        return $this->product->get_type() === 'variable';
    }

    /**
     * @return bool
     */
    public function isSimple()
    {
        return $this->product->get_type() === 'simple';
    }

    /**
     * @return bool
     */
    public function isVariation()
    {
        return $this->product->get_type() === 'variation';
    }

    /**
     * Check that product is available to process
     * @return bool
     */
    public function isProcessable()
    {
        return $this->isSimple || $this->isVariation;
    }

    /**
     * Return array of variations
     * @return UPF_Product[]
     */
    public function getVariables()
    {
        if (!$this->isVariable) {
            return array();
        }

        /** @var WC_Product_Variable $product */
        $product = $this->product;

        $childs = array();

        foreach ($product->get_visible_children() as $childID) {
            $childs[] = new UPF_Product($this->core, new WC_Product_Variation($childID));
        }

        return $childs;
    }

    /**
     * Process product data
     * @return bool
     * @throws Exception
     */
    public function process()
    {
        $product = $this->product;

        if (!$product->get_id() || !$this->isProcessable) {
            return false;
        }

        $sku = $product->get_sku();

        if ($this->isVariation && !$sku) {
            $parentData = $product->get_parent_data();
        }

        $config_main_attrs = array(
            'id' => $this->core->getConfig()->get("attributes/id", false),
            'name' => $this->core->getConfig()->get("attributes/name", false),
            'description' => $this->core->getConfig()->get("attributes/description", false),
        );

        $this->id = $this->resolveAttribute($config_main_attrs['id'], $product);
        $this->name = $this->resolveAttribute($config_main_attrs['name'], $product);
        $this->description = $this->resolveAttribute($config_main_attrs['description'], $product);
        $this->link = get_permalink($product->get_id());

        $this->processPrices();
        $this->processCategories();
        $this->processImages();
        $this->processAttributes();
        $this->processDimensions();
        $this->processConfigurableFields();

        return true;
    }

    /**
     * Process product prices
     */
    protected function processPrices()
    {
        $currency = get_option('woocommerce_currency');
        $tax = $this->getTax();
        $regular = $this->product->get_regular_price();
        $value = $regular + $regular * $tax;

        $prices = array(
            array(
		        'currency' => $currency,
		        'value'    => (int) ($value * 100),
		        'type'     => 'regular',
                'vat'      => $tax * 10000
            ),
        );

        if ($sale = $this->product->get_sale_price()) {
            $sale += $sale * $tax;
            $prices[] = array(
                'currency' => $currency,
                'value'    => (int) $sale,
                'type'     => 'sale',
                'vat'      => $tax * 10000,
            );
        }

        $this->prices = $prices;
    }

    /**
     * Tax handler
     */
    protected function getTax()
    {
        $code = $this->core->getConfig()->getSelect("filter/countries", array());

        $country = WC_Tax::find_rates(array(
            'country' => $code[0],
        ));

        if (count($country) == 0) {
            if (count(WC_Tax::get_base_tax_rates()) == 0) {
                return 0;
            }

            $country = WC_Tax::get_base_tax_rates();
        }

        $lastCountry = array_pop($country);

        $unformatted_tax = $lastCountry['rate'];
        $tax = number_format($unformatted_tax, 2, '.', '') / 100;

        return $tax;
    }

    /**
     * Process product categories
     */
    protected function processCategories()
    {
        $categories = array();
        $categoryIds = $this->product->get_category_ids();

        foreach ($categoryIds as $categoryId) {
            $this->processCategoryWithParent($categoryId, $categories);
        }

        if (!empty($categories)) {
            sort($categories);
            $this->categories = $categories;
        }
    }

    /**
     * Helper function
     * Fetch category with parents
     * @param int $categoryID
     * @param array &$categories
     * @return bool
     */
    protected function processCategoryWithParent($categoryID, &$categories)
    {
        $term = get_term_by('id', $categoryID, 'product_cat');

        if (!$term) {
            return false;
        }

        if (isset($categories[$term->term_id])) {
            return true;
        }

        $category = array(
            'id'   => $term->term_id,
            'name' => $term->name,
        );

        if ($term->parent && $this->processCategoryWithParent($term->parent, $categories)) {
            $category['parent_id'] = $term->parent;
        }

        $categories[$term->term_id] = $category;

        return true;
    }

    /**
     * Process product Dimensions
     */
    protected function processDimensions()
    {
        $config_dimension_attrs = array(
            'height_val' => $this->core->getConfig()->get("attributes/heightValue", false),
            'height_unit' => $this->core->getConfig()->get("attributes/heightUnit", false),
            'length_val' => $this->core->getConfig()->get("attributes/lengthValue", false),
            'length_unit' => $this->core->getConfig()->get("attributes/lengthUnit", false),
            'width_val' => $this->core->getConfig()->get("attributes/widthValue", false),
            'width_unit' => $this->core->getConfig()->get("attributes/widthUnit", false),
            'weight_val' => $this->core->getConfig()->get("attributes/weightValue", false),
            'weight_unit' => $this->core->getConfig()->get("attributes/weightUnit", false),
        );

        $product = $this->product;

        $dimensions = array();

        if ($height = $this->resolveAttribute($config_dimension_attrs['height_val'], $product)) {
            $dimensions['height'] = array(
                'value' => $height,
                'unit'  => $this->resolveAttribute($config_dimension_attrs['height_unit'], $product),
            );
        }

        if ($length = $this->resolveAttribute($config_dimension_attrs['length_val'], $product)) {
            $dimensions['length'] = array(
                'value' => $length,
                'unit'  => $this->resolveAttribute($config_dimension_attrs['length_unit'], $product),
            );
        }

        if ($width = $this->resolveAttribute($config_dimension_attrs['width_val'], $product)) {
            $dimensions['width'] = array(
                'value' => $width,
                'unit'  => $this->resolveAttribute($config_dimension_attrs['width_unit'], $product),
            );
        }

        if ($weight = $this->resolveAttribute($config_dimension_attrs['weight_val'], $product)) {
            $dimensions['weight'] = array(
                'value' => $weight,
                'unit'  => $this->resolveAttribute($config_dimension_attrs['weight_unit'], $product),
            );
        }

        if (!empty($dimensions)) {
            $this->dimensions = $dimensions;
        }
    }

    /**
     * Process product images
     */
    protected function processImages()
    {
        $product = $this->product;

        // Process main image
        $mainImage = wp_get_attachment_image_src($product->get_image_id(), 'full');

        if (!empty($mainImage)) {
            $this->image_link = $mainImage[0];
        }

        // Process additional images
        $additionalImages = array();
        $wpImageIds = $product->get_gallery_image_ids();

        foreach ($wpImageIds as $imageId) {
            $additionalImage = wp_get_attachment_image_src($imageId, 'full');

            if (empty($additionalImage) || $this->image_link === $additionalImage[0]) {
                continue;
            }

            $additionalImages[] = $additionalImage[0];
        }

        if (!empty($additionalImages)) {
            $this->additional_image_links = $additionalImages;
        }
    }

    /**
     * Process product attributes
     */
    protected function processAttributes()
    {
        $attributes = $this->core->getConfig()->getSelect("attributes/additional", array());

        $product = $this->product;

        foreach ($attributes as $attribute_name) {
            $attribute_name = str_replace(' ', '_', $attribute_name);

            $attr = $product->get_attribute($attribute_name);

            if ($attr) {
                $additional_attributes[] = array(
                    'name'  => $attribute_name,
                    'type'  => 'string',
                    'value' => $attr,
                );
            }
        }

        if (!empty($additional_attributes)) {
            $this->attributes = $additional_attributes;
        }
    }

    /**
     * Process configurable fields (associated custom product attributes)
     */
    protected function processConfigurableFields()
    {
        foreach (array(
            'color', 'gender', 'size', 'material', 'pattern',
            'age group', 'condition', 'sizeType', 'sizeSystem',
        ) as $name) {
            $key = str_replace(" ", "_", $name);

            $attribute_name = $this->core->getConfig()->get("attributes/{$key}", false);
            $attribute_value = $this->resolveAttribute($attribute_name, $this->product);

            if ($attribute_name != 'Not selected' && $attribute_value) {
                $this->$key = $attribute_value;
            }
        }
    }

    protected function resolveAttribute($attribute, $product)
    {
        $parsed_attr = explode('.', $attribute);
        $type = $parsed_attr[0];
        $name = $parsed_attr[1];

        switch ($type) {
            case 'calc':
                $result = call_user_func(array($product, 'get_' . $name));
                break;
            case 'db':
		if ($name == '_dimension_unit' || $name == '_weight_unit')
		    $result = get_option('woocommerce' . $name);
		else
            	    $result = get_post_meta($product->id, $name);
                break;
            default:
                $result = $product->get_attribute($name);
                break;
        }

        return $result;
    }

    /**
     * Get product data for feed
     * @return array
     * @throws Exception
     */
    public function toArray()
    {
        if (empty($this->data)) {
            $this->process();
        }

        return $this->data;
    }

    /**
     * Get feed product data fields
     * @param string $name
     * @return mixed|null
     */
    public function __get($name)
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }

        if (stripos($name, 'is') === 0 && method_exists($this, $name)) {
            return $this->{$name}();
        }

        $getMethod = "get{$name}";

        if (method_exists($this, $getMethod)) {
            return $this->{$getMethod}();
        }

        return null;
    }

    /**
     * Set feed product data fields
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $setMethod = "set{$name}";

        if (method_exists($this, $setMethod)) {
            $this->{$setMethod}($value);

            return;
        }

        $this->data[$name] = $value;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return $this|mixed|null
     * @throws Exception
     */
    public function __call($name, $arguments)
    {
        $property = strtolower(preg_replace("/^(unset|get|set)/", '', $name));
        $propertyExist = isset($this->data[$property]);

        if ($propertyExist) {
            if (stripos($name, 'unset') === 0) {
                unset($this->data[$property]);

                return $this;
            }

            if (stripos($name, 'get') === 0) {
                return $this->{$property};
            }

            if (stripos($name, 'set') === 0 && isset($arguments[0])) {
                $this->{$property} = $arguments[0];

                return $this;
            }
        }

        throw new Exception("Unknown method {$name}");
    }
}
