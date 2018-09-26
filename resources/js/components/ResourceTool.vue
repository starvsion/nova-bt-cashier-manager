<script type="text/ecmascript-6">
    export default {
        props: ['resourceName', 'resourceId', 'field'],

        data(){
            return {
                loading: true,
                user: null,
                subscriptions: null,
            }
        },


        computed: {
            basePath() {
                return Nova.config.base;
            }
        },


        mounted() {
            this.loadUserData();
        },


        methods: {
            loadUserData(){
                axios.get(`/nova-cashier-tool-api/billable/${this.resourceId}/?brief=true`)
                        .then(response => {
                            this.user = response.data.user;
                            this.subscriptions = response.data.subscriptions;

                            this.loading = false;
                        });
            }
        }
    }
</script>

<template>
    <div >
        <loading-view :loading="loading">
            <div class="text-90" v-if="!subscriptions">
                User has no subscriptions.
            </div>

            <div style="border-bottom: 2px solid lightgray;" v-else  v-for="subscription in subscriptions" >
                <div class="flex border-b border-40">
                    <div class="w-1/4 py-4"><h4 class="font-normal text-80">Plan</h4></div>
                    <div class="w-3/4 py-4"><p class="text-90">
                        {{subscription.plan}}
                        ({{subscription.plan_amount }} {{subscription.plan_currency}} / {{subscription.plan_frequency}} time(s) On the {{subscription.plan_interval}}th each Month(s))
                    </p></div>
                </div>

                <div class="flex border-b border-40 mb-5" v-if="subscription">
                    <div class="w-1/4 py-4"><h4 class="font-normal text-80">Subscribed since</h4></div>
                    <div class="w-3/4 py-4"><p class="text-90">{{subscription.created_at}}</p></div>
                </div>

                <div class="flex border-b border-40" v-if="subscription">
                    <div class="w-1/4 py-4"><h4 class="font-normal text-80">Next Billing Date</h4></div>
                    <div class="w-3/4 py-4"><p class="text-90">{{subscription.next_billing_date}} </p></div>
                </div>

                <div class="flex border-b border-40 remove-bottom-border" v-if="subscription">
                    <div class="w-1/4 py-4"><h4 class="font-normal text-80">Status</h4></div>
                    <div class="w-3/4 py-4">
                        <p class="text-90">
                            <span v-if="subscription.on_grace_period">On Grace Period</span>
                            <span v-if="subscription.cancelled || subscription.cancel_at_period_end" class="text-danger">Cancelled</span>
                            <span v-if="subscription.active && !subscription.cancelled && !subscription.cancel_at_period_end">Active</span>
                            ·
                            <a class="text-primary no-underline" :href="basePath+'/cashier-tool/billable/'+resourceId+'/?subscription_id='+subscription.stripe_plan">
                                Manage
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </loading-view>
    </div>
</template>

<style>
    /* Scopes Styles */
</style>
