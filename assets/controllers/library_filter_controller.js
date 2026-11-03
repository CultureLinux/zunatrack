import { Controller } from '@hotwired/stimulus';
export default class extends Controller { connect(){this.timer=null} submit(){this.element.requestSubmit()} search(){clearTimeout(this.timer);this.timer=setTimeout(()=>this.element.requestSubmit(),300)} disconnect(){clearTimeout(this.timer)} }
