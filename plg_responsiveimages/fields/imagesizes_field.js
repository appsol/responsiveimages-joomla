
imageSizes = {
    newSlide: function (){
	var last = document.id('sizes_list').getLast('li');
	//    var last_li = pages_list.getLast('li');
	var newSlide = last.clone();
	var index = Number(String(last.id).substring(String(last.id).lastIndexOf('_') + 1, String(last.id).length + 1));
	if(!isNaN(index)){
	    index++;
	    newSlide.id = 'imagesize_' + index;
	    var fields = newSlide.getElements('input')
	    // update each new input field
	    fields.each(function(field, i){
		imageSizes.setInput(field, index)
	    })
	    newSlide.inject(last, 'after');
	}
    },
    setInput: function (field, index)
    {
	// Prefixes for this JFormfield
	var idPrefix = 'jform_params_imagesizes_';
	var namePrefix = 'jform[params][imagesizes]';
	var fieldName = String(field.get('name')).substring(String(field.get('name')).lastIndexOf('[') + 1, String(field.get('name')).length - 1);
	// Set the new properties of the created input
	field.setProperties({
	    id: idPrefix + index + '_' + fieldName,
	    name: namePrefix + '[' + index + '][' + fieldName + ']',
	    value: ''
	})
    },
    removeSlide: function (button)
    {
	button.getParent().destroy();
    }
}