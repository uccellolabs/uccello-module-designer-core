window.Vue = require('vue');

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

// import ModuleDesigner from './components/DesignerComponent.vue'
// import UitypeComponent from './components/UitypeComponent.vue'

// import axios from 'axios'

Vue.component('module-designer', require('./components/DesignerComponent.vue'))
Vue.component('uitype', require('./components/UitypeComponent.vue'))


// Vue.component('async-example', function (resolve, reject) {

//   axios.get('/default/user/uitypes').then((response) => {
//     let template = response.data

//     resolve({
//       template: template
//     })
//   })

// })

const app = new Vue({
    el: '#app-module-designer',
    // components: {
    //     'uitype': UitypeComponent,
    //     'module-designer': ModuleDesigner
    // }
});
