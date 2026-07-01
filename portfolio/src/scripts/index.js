import "../styles/style.scss";

// init application
class App {
  constructor() {
    let ratingList = document.querySelectorAll('.rating-stars');
    let w = ratingList[0].querySelectorAll('li')[0].getBoundingClientRect().width;
    let green = '#00d929';

    ratingList.forEach(el => {
      let score = parseFloat(el.dataset.score);
      let s = w * score;
      let last = Math.trunc(score);
      let p = last === 5 ? false : Math.trunc((score - last) * 100);

      if (p) {
        el.querySelectorAll('li')[last].style.background = `linear-gradient(to right, ${green} ${p}%, transparent ${p}%, transparent 100%)`;
      }
    });
  }
}

new App();
