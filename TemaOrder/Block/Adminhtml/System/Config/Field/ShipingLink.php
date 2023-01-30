<?php

namespace Cypisnet\TemaOrder\Block\Adminhtml\System\Config\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;

/**
 * Backend system config array field renderer.
 */
class ShipingLink extends AbstractFieldArray
{
  
    /**
     * ShipingLink constructor.
     *
     * @param ImageRenderer $imageRenderer
     * @param Context       $context
     * @param array         $data
     */
     protected $_groupRenderer;
     
     
    public function __construct(
        Context $context,
        array $data = []
    )
    {
        parent::__construct($context, $data);
    }

    /**
     * @inheritdoc
     */
    protected function _prepareToRender()
    {
        $this->addColumn(
        		's_code', 	['label' => __('Shipping Code'),
        		'size' => '15',
        		'renderer' => $this->_getGroupRenderer()
        		]
        		);
        $this->addColumn('t_code',  ['label' => __('TEMA ERP Code'), 'size' => '15']);
       
        $this->_addAfter = false;
    }

	protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];

        $tax = $row->getSCode();
        if ($tax !== null) {
            $options['option_' . $this->_getGroupRenderer()->calcOptionHash($tax)] = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }
    
    protected function _getCellInputElementName($columnName)
    {
       
        return parent::_getCellInputElementName($columnName);
    }
    
    /**
     * Retrieve group column renderer
     *
     * @return Inputgroup
     */
    protected function _getGroupRenderer()
    {
        if (!$this->_groupRenderer) {
            $this->_groupRenderer = $this->getLayout()->createBlock(
                \Cypisnet\TemaOrder\Block\Adminhtml\System\Config\Field\DropdownValues::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
            $this->_groupRenderer->setClass('dropdown_group_select');
        }
        return $this->_groupRenderer;
    }


}
