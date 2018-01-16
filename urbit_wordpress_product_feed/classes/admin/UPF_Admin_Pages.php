<?php

if (!defined( 'URBIT_PRODUCT_FEED_PLUGIN_DIR' )) {
    exit;
}

/**
 * Class UPF_Admin_Pages
 */
class UPF_Admin_Pages
{
    /**
     * @var array
     */
    protected $pages;

    /**
     * @var UPF_Admin_Menu
     */
    protected $menu;

    /**
     * @var array
     */
    protected $menuElements;

    /**
     * UPF_Admin_Pages constructor.
     * Init all pages
     *
     * @param UPF_Admin_Page_Abstract[] $pages
     */
    public function __construct($pages)
    {
        foreach ($pages as $page) {
            $childs = $page->getChildPages();

            if ($page instanceof UPF_Admin_Page_Abstract && !empty($childs)) {
                $menuElement = $page->getMenuElement();
                $this->pages[] = $page;
                $this->menuElements[] = $menuElement;

                foreach ($childs as $childPage) {
                    $childPage->getMenuElement()->setParentMenuElement($menuElement);
                    $this->menuElements[] = $childPage->getMenuElement();
                }
            }
        }

        $this->menu = new UPF_Admin_Menu($this->menuElements);
    }

    /**
     * @return array
     */
    public function getMenuElements()
    {
        return $this->menuElements;
    }

    /**
     * @return UPF_Admin_Menu
     */
    public function getMenu()
    {
        return $this->menu;
    }

    /**
     * @return array
     */
    public function getPages()
    {
        return $this->pages;
    }
}
