Nova.booting((Vue, router) => {
    Vue.component('cashier-tool', require('./components/ResourceTool'));
    
    router.addRoutes([
        {
            name: 'cashier-tool-billable',
            path: '/cashier-tool/billable/:userId',
            component: require('./components/UserDetails'),
            props: true
        },
    ])
})
