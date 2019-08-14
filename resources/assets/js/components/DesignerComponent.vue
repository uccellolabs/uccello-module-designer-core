<template>
    <form class="edit-form">
        <div class="row clearfix">
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                <div class="card">
                    <div class="header">
                        <h2>
                            BASIC EXAMPLES
                        </h2>
                    </div>
                    <div class="body">
                        <uitype></uitype>
                    </div>
                </div>
            </div>
        </div>

        <button v-on:click="update" class="btn btn-success">coucou</button>
    </form>
</template>

<script>
    export default {
        mounted: function() {
            console.log('Component mounted.')
        },
        data () {
            return {

            }
        },
        methods: {
            update: function (event) {
                event.preventDefault();

                $('form.edit-form label').text('youpi !!!')
            }
        }
    }
</script>
