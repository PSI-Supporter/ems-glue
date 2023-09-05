function setInnerHTML(elm, html) {
    elm.innerHTML = html;

    Array.from(elm.querySelectorAll("script"))
        .forEach(oldScriptEl => {
            const newScriptEl = document.createElement("script");

            Array.from(oldScriptEl.attributes).forEach(attr => {
                newScriptEl.setAttribute(attr.name, attr.value)
            });

            const scriptText = document.createTextNode(oldScriptEl.innerHTML);
            newScriptEl.appendChild(scriptText);

            oldScriptEl.parentNode.replaceChild(newScriptEl, oldScriptEl);
        });
}

function tribinClearTextBox() {
    let textBoxInputList = document.getElementsByClassName('form-control')
    let textBoxInputLength = textBoxInputList.length
    for (let i = 0; i < textBoxInputLength; i++) {
        textBoxInputList[i].value = ''
    }
}

function tribinClearTextBoxByClassName(ClassName) {
    let textBoxInputList = document.getElementsByClassName(ClassName)
    let textBoxInputLength = textBoxInputList.length
    for (let i = 0; i < textBoxInputLength; i++) {
        textBoxInputList[i].value = ''
    }
}

function tribinPWValidator(pvalue) {
    let numberList = [...Array(10).keys()]
    let specialCharList = ['~','!','@','#','$','%','^','&','*','(',')','_','+',':','"','<','>','?','{','}','|']
    if(pvalue.trim().length<8){
        return {cd: '0', msg : 'At least 8 characters'}
    }
    let isFound = false
    for(let i=0; i<numberList.length; i++) {
        if(pvalue.includes(numberList[i])) {
            isFound = true
            break
        }
    }
    if(!isFound) {
        return {cd: '0', msg : 'At least 1 numerical character'}
    }
    isFound = false
    for(let i=0; i<specialCharList.length; i++) {
        if(pvalue.includes(specialCharList[i])) {
            isFound = true
            break
        }
    }
    if(!isFound) {
        return {cd: '0', msg : 'At least 1 special character'}
    }
    return  {cd: '1', msg : 'OK'}
}