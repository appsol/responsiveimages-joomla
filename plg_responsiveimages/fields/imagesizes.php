<?php

defined('_JEXEC') or die('Restricted access');
 
jimport('joomla.html.html');
jimport('joomla.form.formfield');

class JFormFieldImagesizes extends JFormField
{
    protected $type = "imagesizes";
    public function getLabel()
    {
        return '<p>'.parent::getLabel() .
            '<span class="spacer"><span class="before"></span><span><hr /></span><span class="after"></span></span></p>';
    }
    public function getInput()
    {
        $number = ($this->value && count($this->value))? count($this->value) : 1; // how many to output initially
        $html = array();
        $html[] = '<ul class="adminformlist" id="sizes_list">';
        for($i = 0; $number > $i; $i++)
        {
            $name_prefix = $this->name.'['.$i.']';
            if(isset($this->value[$i]))
            {
                $value = $this->value[$i];
            
                $name = $value->name;
                $width = $value->width;
                $height = $value->height;
                $resolution = $value->resolution;
            }
            else
            {
                $name = ''; $width = ''; $height = ''; $resolution = ''; 
            }
            
            $html[] = '<li id="imagesize_' . $i . '">';
            $html[] = '<p><label class="hasTip" title="'. JText::_('FIELD_IMAGESIZE_DESC') . '">'. JText::_('FIELD_IMAGESIZE_LABEL') . '</label><input type="text" id="'.$this->id.'_name_' . $i . '" name="'.$name_prefix.'[name]" value="'.$name.'"/></p>';
            $html[] = '<p><label class="hasTip" title="'. JText::_('FIELD_IMAGEWIDTH_DESC') . '">'. JText::_('FIELD_IMAGEWIDTH_LABEL') . '</label><input type="text" id="'.$this->id.'_width_' . $i . '" name="'.$name_prefix.'[width]" value="'.$width.'"/></p>';
            $html[] = '<p><label class="hasTip" title="'. JText::_('FIELD_IMAGEHEIGHT_DESC') . '">'. JText::_('FIELD_IMAGEHEIGHT_LABEL') . '</label><input type="text" id="'.$this->id.'_height_' . $i . '" name="'.$name_prefix.'[height]" value="'.$height.'"/></p>';
            $html[] = '<p><label class="hasTip" title="'. JText::_('FIELD_IMAGERESOLUTION_DESC') . '">'. JText::_('FIELD_IMAGERESOLUTION_LABEL') . '</label><input type="text" id='.$this->id.'_resolution_' . $i . '" name="'.$name_prefix.'[resolution]" value="'.$resolution.'" /></p>';
            $html[] = '<button class="btn_remove" type="button" onclick="imageSizes.removeSlide(this)">'. JText::_('BTN_REMOVESIZE') . '</button>';
            $html[] = '<span class="spacer"><span class="before"></span><span><hr /></span><span class="after"></span></span>';
            $html[] = '</li>';
        }
        $html[] = '</ul><button class="btn_add" type="button" onclick="imageSizes.newSlide()">'. JText::_('BTN_ADDSIZE') . '</button>';
        
        JFormFieldImagesizes::loadScript();
        
        return implode("\n", $html);
    }
    private function loadScript()
    {
         JHtml::_('behavior.modal');
         
         JFactory::getDocument()->addScript('/plugins/content/contentpicturefill/fields/imagesizes_field.js');
    }
}
?>
