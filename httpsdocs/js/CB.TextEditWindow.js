Ext.namespace('CB');

Ext.define('CB.TextEditWindow', {
    extend: 'Ext.Window'
    ,border: false
    ,bodyBorder: false
    ,closable: true
    ,closeAction: 'hide'
    ,hideCollapseTool: true
    ,layout: 'fit'
    ,maximizable: false
    ,minimizable: false
    ,modal: true
    ,resizable: true
    ,stateful: false
    ,data: { callback: Ext.emptyFn }
    ,title: L.EditingValue
    ,width: 600
    ,height: 400

    ,initComponent: function() {
        this.data = this.config.data;

        this.editor = new Ext.form.TextArea({
            border: false
        });

        Ext.apply(this, {
            layout: 'fit'
            ,items: [this.editor]
            ,keys:[{
                key: Ext.event.Event.ESC,
                fn: this.doClose,
                scope: this
                }
            ]
            ,listeners: {
                scope: this
                ,show: this.onWindowsShow
            }
            ,buttons: [
                {
                    text: Ext.MessageBox.buttonText.ok
                    ,handler: this.doSubmit
                    ,scope: this
                },{
                    text: Ext.MessageBox.buttonText.cancel
                    ,handler: this.doClose
                    ,scope: this
                }
            ]
        });

        this.callParent(arguments);
    }

    ,onWindowsShow: function(){
        //update title if set
        var title = Ext.valueFrom(this.data.title, this.title);
        this.setTitle(title);
        this.getHeader().setTitle(title);

        this.editor.setValue(Ext.valueFrom(this.data.value, ''));
        this.editor.focus(false, 350);
    }

    ,doSubmit: function(){
        var f = Ext.Function.bind(
            this.data.callback
            ,Ext.valueFrom(this.data.scope, this)
            ,[this, this.editor.getValue()]
        );
        f();
        this.doClose();
    }

    ,doClose: function(){
        this.hide();
    }
});
