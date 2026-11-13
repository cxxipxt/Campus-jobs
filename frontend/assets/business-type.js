const businessType = document.getElementById('business_type');
const otherBusinessType = document.getElementById('business_type_other');
const otherBusinessField = document.getElementById('business-type-other');

function updateOtherBusinessField() {
  const isOther = businessType.value === 'อื่น ๆ';
  otherBusinessField.hidden = !isOther;
  otherBusinessType.required = isOther;
}

businessType.addEventListener('change', updateOtherBusinessField);
updateOtherBusinessField();
