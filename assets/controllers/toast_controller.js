import { Controller } from '@hotwired/stimulus';
export default class extends Controller { connect(){this.timeout=setTimeout(()=>this.element.classList.add('is-leaving'),4500);this.element.addEventListener('transitionend',()=>this.element.remove(),{once:true})} disconnect(){clearTimeout(this.timeout)} }
