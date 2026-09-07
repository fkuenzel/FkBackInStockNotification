(()=>{var l=Object.defineProperty;var m=(s,e,t)=>e in s?l(s,e,{enumerable:!0,configurable:!0,writable:!0,value:t}):s[e]=t;var d=(s,e,t)=>m(s,typeof e!="symbol"?e+"":e,t);var r=class extends window.PluginBaseClass{init(){this._feedback=this.el.querySelector(".fk-back-in-stock-notification-feedback"),this._registerForm=this.el.querySelector(".js-bisn-register-form"),this._removeForm=this.el.querySelector(".js-bisn-remove-form"),this._registerForm&&this._registerForm.addEventListener("submit",this._onRegister.bind(this)),this._removeForm&&this._removeForm.addEventListener("submit",this._onRemove.bind(this))}_onRegister(e){e.preventDefault(),this._submit(this._registerForm,this.options.registerUrl)}_onRemove(e){e.preventDefault();let t=this._removeForm.getAttribute("data-bisn-confirm");t&&!window.confirm(t)||this._submit(this._removeForm,this.options.removeUrl)}async _submit(e,t){let i=e.querySelector('button[type="submit"]'),c=e.querySelector(".fk-back-in-stock-notification-spinner");this._setLoading(i,c,!0),this._hideFeedback();try{let n=await fetch(t,{method:"POST",body:new FormData(e),headers:{"X-Requested-With":"XMLHttpRequest",Accept:"application/json"}}),a=await n.json();n.ok&&a.success?(this._showFeedback(a.message,"success"),e.setAttribute("hidden","hidden")):this._showFeedback(a.message||"","danger")}catch{this._showFeedback("","danger")}finally{this._setLoading(i,c,!1)}}_setLoading(e,t,i){e&&(e.disabled=i,e.classList.toggle(this.options.loadingClass,i)),t&&(t.hidden=!i)}_showFeedback(e,t){this._feedback&&(this._feedback.classList.remove("alert-success","alert-danger"),this._feedback.classList.add(t==="success"?"alert-success":"alert-danger"),this._feedback.textContent=e,this._feedback.hidden=!1)}_hideFeedback(){this._feedback&&(this._feedback.hidden=!0)}};d(r,"options",{registerUrl:"",removeUrl:"",loadingClass:"is-loading"});var o=class extends window.PluginBaseClass{init(){this.el.addEventListener("submit",this._onSubmit.bind(this))}_onSubmit(e){let t=this.el.getAttribute("data-bisn-confirm");t&&!window.confirm(t)&&e.preventDefault()}};var h=window.PluginManager;h.register("BackInStockNotification",r,"[data-fk-back-in-stock-notification]");h.register("BackInStockNotificationConfirm",o,"[data-bisn-confirm]");})();
/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * Storefront plugin for the product-detail notification widget. Handles the
 * AJAX registration form and the "unsubscribe" action shown to already
 * registered customers, giving immediate inline feedback without a page reload.
 *
 * @license GPL-2.0-only
 */
/**
 * Adds a confirmation dialog before a delete form is submitted in the customer
 * account area (single delete and delete-all). These forms perform a normal
 * full-page POST; this plugin only guards the submit.
 *
 * @license GPL-2.0-only
 */
