import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
    static targets = ['query', 'clear'];
    connect(){this.timer=null;this.toggleClear()}
    submit(){this.element.requestSubmit()}
    search(){this.toggleClear();clearTimeout(this.timer);this.timer=setTimeout(()=>this.element.requestSubmit(),300)}
    clear(){this.queryTarget.value='';this.toggleClear();this.element.requestSubmit()}
    toggleClear(){this.clearTarget.hidden=this.queryTarget.value.length===0}
    disconnect(){clearTimeout(this.timer)}
}
