// in Symfony framework: app/console assets:install


function validateFormEditWork(){
    
    var msg1 = document.getElementById('myErrorSpan1');
    msg1.innerHTML = '';
    msg1.style.color = 'red';
    msg1.style.marginLeft = '20px';
    msg1.style.fontFamily = '';         // http://www.w3schools.com/jsref/dom_obj_style.asp
    
    var msg2 = document.querySelector('#myErrorSpan2');
    msg2.innerHTML = '';    
    msg2.style.color = 'red';    
    msg2.style.marginLeft = '20px';
    
    if((document.getElementById('name').value) === '' ){        
        msg1.innerHTML = '* please, enter a name';
        return false;
    }
    
    if( ! /^[a-z\-0-9]+$/.test(document.getElementById('slug').value) ){      // [a-z\-0-9] ou [a-z0-9-] ou [-a-z0-9]
        msg2.innerHTML = '* Slug should only contain small letters and/or dashes (e.g. this-is-my-slug)';
        return false;
    }
}

function validateFormEditCategory(){
    $('document').ready(function(){
        $('#myErrorSpan3').css({'color':'red', 'marginLeft':'20px', 'font-weight':'bold'});
        $('#myErrorSpan4').css({'color':'red', 'marginLeft':'20px', 'font-weight':'bold'});
        $('#myErrorSpan3').html('');
        $('#myErrorSpan4').html('');
        if($('#name').val() == ''){
            $('#myErrorSpan3').html("* Please, enter a name for this work.");
            event.preventDefault();
        }
        if( ! /^[a-z\-0-9]+$/.test($('#slug').val())){
            $('#myErrorSpan4').html('* Slug should only contain small letters and/or dashes (e.g. this-is-my-slug)');
            event.preventDefault();
        }
       
    });
    
}
