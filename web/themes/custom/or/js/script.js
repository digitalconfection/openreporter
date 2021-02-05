
var elements = document.getElementsByClassName('frame-link');

var showCard = function() {
  document.getElementById('modal').style.display = 'block';
  document.body.style.overflow = 'hidden';
};

for (var i = 0; i < elements.length; i++) {
  elements[i].addEventListener('click', showCard, false);
}

function closeCard() {
  document.getElementById('modal').style.display = 'none';
  document.document.getElementsByTagName('body').style.overflow = 'scroll';
}