define({
	'ProctorManager' : {
        'actions' : {
            'index' : 'controller/ProctorManager/index'
        }
    },
    'Diagnostic' : {
        'actions' : {
            'index' : 'controller/Diagnostic/index',
            'diagnostic' : 'controller/Diagnostic/diagnostic',
            'deliveriesByProctor' : 'controller/Diagnostic/deliveriesByProctor'
        }
    },
    'Reporting' : {
        'actions' : {
            'sessionHistory' : 'controller/Reporting/history'
        }
    },
    'Irregularity' : {
        'actions' : {
            'index' : 'controller/Irregularity/index'
        }
    },
    'DeliverySelection' : {
        'actions' : {
            'index' : 'controller/Delivery/index',
        }
    },
    'Monitor' : {
        'actions' : {
            'index' : 'controller/Delivery/monitoring',
        }
    },
    'Delivery' : {
        'actions' : {
            'index' : 'controller/Delivery/index',
            'manage' : 'controller/Delivery/manage',
            'monitoring' : 'controller/Delivery/monitoring',
            'monitoringAll' : 'controller/Delivery/monitoring',
            'testTakers' : 'controller/Delivery/testTakers'
        }
    }
});
