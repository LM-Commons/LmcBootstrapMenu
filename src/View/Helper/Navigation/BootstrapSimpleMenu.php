<?php
namespace LmcBootstrapMenu\View\Helper\Navigation;

use Laminas\View\Helper\Navigation\Menu as LaminasMenu;
use Laminas\Navigation\AbstractContainer;
use RecursiveIteratorIterator;
use Laminas\Navigation\Page\AbstractPage;
use Laminas\View\Helper\Navigation\HelperInterface;

class BootstrapSimpleMenu extends LaminasMenu implements HelperInterface
{
    
    /**
     * Renders a normal menu (called from {@link renderMenu()})
     *
     * @param  AbstractContainer $container          container to render
     * @param  string            $ulClass            CSS class for first UL
     * @param  string            $indent             initial indentation
     * @param  int|null          $minDepth           minimum depth
     * @param  int|null          $maxDepth           maximum depth
     * @param  bool              $onlyActive         render only active branch?
     * @param  bool              $escapeLabels       Whether or not to escape the labels
     * @param  bool              $addClassToListItem Whether or not page class applied to <li> element
     * @param  string            $liActiveClass      CSS class for active LI
     * @return string
     */
    protected function renderNormalMenu(
        AbstractContainer $container,
        $ulClass,
        $indent,
        $minDepth,
        $maxDepth,
        $onlyActive,
        $escapeLabels,
        $addClassToListItem,
        $liActiveClass
    ) {
        $html = '';
    
        // find deepest active
        $found = $this->findActive($container, $minDepth, $maxDepth);
        /* @var $escaper \Laminas\View\Helper\EscapeHtmlAttr */
        $escaper = $this->view->plugin('escapeHtmlAttr');
    
        if ($found) {
            $foundPage  = $found['page'];
            $foundDepth = $found['depth'];
        } else {
            $foundPage = null;
        }
    
        // create iterator
        $iterator = new RecursiveIteratorIterator(
            $container,
            RecursiveIteratorIterator::SELF_FIRST
        );
        if (is_int($maxDepth)) {
            $iterator->setMaxDepth($maxDepth);
        }
    
        // iterate container
        $prevDepth = -1;
        foreach ($iterator as $page) {
            $depth = $iterator->getDepth();
            $isActive = $page->isActive(true);
            if ($depth < $minDepth || !$this->accept($page)) {
                // page is below minDepth or not accepted by acl/visibility
                continue;
            } elseif ($onlyActive && !$isActive) {
                // page is not active itself, but might be in the active branch
                $accept = false;
                if ($foundPage) {
                    if ($foundPage->hasPage($page)) {
                        // accept if page is a direct child of the active page
                        $accept = true;
                    } elseif ($foundPage->getParent()->hasPage($page)) {
                        // page is a sibling of the active page...
                        if (!$foundPage->hasPages(!$this->renderInvisible) ||
                            is_int($maxDepth) && $foundDepth + 1 > $maxDepth) {
                                // accept if active page has no children, or the
                                // children are too deep to be rendered
                                $accept = true;
                            }
                    }
                }
    
                if (!$accept) {
                    continue;
                }
            }
    
            if(strcmp('--devider--',$page->getLabel()) === 0 ){
                $html .= '<div class="dropdown-divider"></div>';
                continue;
            }

            // make sure indentation is correct
            $depth -= $minDepth;
            $myIndent = $indent . str_repeat('        ', $depth);
    
            if ($depth > $prevDepth) {
                // start new ul tag
                if ($ulClass && $depth ==  0) {
                    $ulClass = ' class="' . $escaper($ulClass) .'"';
                } else {
                    $ulClass = ' class="dropdown-menu dropright "';
                }
                if($depth){
                    $html .= $myIndent . '<div' . $ulClass . ' ">' . PHP_EOL;
                }else{
                    $html .= $myIndent . '<ul' . $ulClass . ' ">' . PHP_EOL;
                }
                
            } elseif ($prevDepth > $depth) {
                // close li/ul tags until we're at current depth
                for ($i = $prevDepth; $i > $depth; $i--) {
                    $ind = $indent . str_repeat('        ', $i);
                    $html .= $ind . '    </div>' . PHP_EOL;
                }
                // close previous li tag
                $html .= $myIndent . '    </li>' . PHP_EOL;
            } 
    
            // render li tag and page
            $liClasses = [];
            // Is page active?
            if ($isActive) {
                $liClasses[] = $liActiveClass;
            }
            // Add CSS class from page to <li>
            if ($addClassToListItem && $page->getClass()) {
                $liClasses[] = $page->getClass();
            }
            
            $additionAttributes = $drownDownClass = '';
            if( $page->hasPages(!$this->renderInvisible) ){
                $drownDownClass = 'dropdown ';
                $additionAttributes = ' ';
            }
            $liClass = ' class="nav-item ' . $drownDownClass . $escaper(implode(' ', $liClasses)) . '"';
            
            if($depth){
                $html .= '    ' . PHP_EOL
                . $myIndent . '        ' . $this->htmlify($page, $escapeLabels, $addClassToListItem, $depth) . PHP_EOL;
            }else{
                $html .= $myIndent . '    <li' . $liClass . '">' . PHP_EOL
                . $myIndent . '        ' . $this->htmlify($page, $escapeLabels, $addClassToListItem, $depth) . PHP_EOL 
                . PHP_EOL;
                if(!$page->hasPages(!$this->renderInvisible)){
                    $html .= PHP_EOL . '</li>';
                }
            }
            
            // store as previous depth for next iteration
            $prevDepth = $depth;
        }
    
        if ($html) {
            // done iterating container; close open ul/li tags
            for ($i = $prevDepth+1; $i > 0; $i--) {
                $myIndent = $indent . str_repeat('        ', $i-1);
                $html .= 
                $myIndent . '</ul>' . PHP_EOL;
            }
            $html = rtrim($html, PHP_EOL);
        }
        
        return $html;
    }
    
    public function htmlify(AbstractPage $page, $escapeLabel = true, $addClassToListItem = false, $depth = 0)
    {
        // get attribs for element
        $attribs = [
            'id'     => $page->getId(),
            'title'  => $this->translate($page->getTitle(), $page->getTextDomain()),
        ];
    
        if ($addClassToListItem === false) {
            $attribs['class'] = $page->getClass();
        }
    
        // does page have a href?
        $href = $page->getHref();
        if ($href) {
            $element = 'a';
            $attribs['href'] = $href;
            $attribs['target'] = $page->getTarget();
        } else {
            $element = 'span';
        }
    
        $anchorAttributes = $anchorDropdownIcon = '';
        if($page->hasPages(!$this->renderInvisible)){
            $anchorDropdownIcon = ' dropdown-toggle';
            $anchorAttributes = ' data-toggle="dropdown" aria-haspopup="true"';
        }
        
        $html  = '<' . $element . $this->htmlAttribs($attribs) . 
            ' class="nav-link' . $anchorDropdownIcon .'"' . $anchorAttributes .'>';
        $label = $this->translate($page->getLabel(), $page->getTextDomain());
        if ($escapeLabel === true) {
            /** @var \Laminas\View\Helper\EscapeHtml $escaper */
            $escaper = $this->view->plugin('escapeHtml');
            $html .= $escaper($label);
        } else {
            $html .= $label ;
        }
        
        $html .= '</' . $element . '>';
    
        return $html;
    }
}

//main-menu
