<template>
  <div v-html="content"></div>
</template>

<script>
import axios from 'axios'

export default {
    mounted: function() {
        axios.get('/default/user/uitypes').then((response) => {
          this.content = response.data
        })
    },
    data () {
        return {
            content: ''
        }
    },
    methods: {

    }
}
</script>
