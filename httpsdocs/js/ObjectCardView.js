Ext.namespace('CB');

Ext.define('CB.ObjectCardView', {
    extend: 'Ext.Panel'
    ,border: false
    ,layout: 'card'
    ,activeItem: 0
    ,tbarCssClass: 'x-panel-white'
    ,loadedData: {}
    ,history: []

    ,initComponent: function() {
        this.instanceId = Ext.id();
        this.actions = {
            back: new Ext.Action({
                iconCls: 'ib-back'
                ,id: 'back' + this.instanceId
                ,scale: 'large'
                ,disabled: true
                ,scope: this
                ,handler: this.onBackClick
            })
            ,edit: new Ext.Action({
                iconCls: 'ib-edit-obj'
                ,id: 'edit' + this.instanceId
                ,scale: 'large'
                ,text: L.Edit
                ,disabled: true
                ,scope: this
                ,handler: this.onEditClick
            })
            ,download: new Ext.Action({
                qtip: L.Download
                ,id: 'download' + this.instanceId
                ,iconAlign:'top'
                ,scale: 'large'
                ,iconCls: 'ib-download'
                ,hidden: true
                ,scope: this
                ,handler: this.onDownloadClick
            })

            ,search: new Ext.Action({
                iconCls: 'ib-search'
                ,id: 'search' + this.instanceId
                ,scale: 'large'
                ,tooltip: L.Search
                ,hidden: true
                ,scope: this
                ,handler: this.onSearchClick
            })
            ,save: new Ext.Action({
                iconCls: 'ib-save'
                ,id: 'save' + this.instanceId
                ,scale: 'large'
                ,text: L.Save
                ,hidden: true
                ,scope: this
                ,handler: this.onSaveClick
            })
            ,cancel: new Ext.Action({
                iconCls: 'ib-cancel'
                ,id: 'cancel' + this.instanceId
                ,scale: 'large'
                ,text: Ext.MessageBox.buttonText.cancel
                ,hidden: true
                ,scope: this
                ,handler: this.onCancelClick
            })
            ,fitImage: new Ext.Action({
                iconCls: 'ib-fit'
                ,id: 'fitImage' + this.instanceId
                ,scale: 'large'
                ,hidden: true
                ,enableToggle: true
                ,pressed: true
                ,scope: this
                ,handler: this.onFitImageClick
            })
            ,openInTabsheet: new Ext.Action({
                iconCls: 'ib-external'
                ,id: 'openInTabsheet' + this.instanceId
                ,scale: 'large'
                ,hidden: true
                ,scope: this
                ,handler: this.onOpenInTabsheetClick
            })

            ,completeTask: new Ext.Action({
                iconCls: 'ib-task-complete'
                ,id: 'completetask' + this.instanceId
                ,scale: 'large'
                ,text: L.Done
                ,hidden: true
                ,scope: this
                ,handler: this.onCompleteTaskClick
            })
            ,preview: new Ext.Action({
                iconCls: 'ib-preview'
                ,id: 'preview' + this.instanceId
                ,enableToggle: true
                ,scale: 'large'
                ,qtip: L.Preview
                ,hidden: true
                ,scope: this
                ,handler: this.onPreviewClick
            })
        };

        this.menuItemConfigs = {
            reload: {
                iconCls: 'i-refresh'
                ,id: 'reload' + this.instanceId
                ,text: L.Refresh
                ,scope: this
                ,handler: this.onReloadClick
            }
            ,'delete': {
                id: 'delete' + this.instanceId
                ,text: L.Delete
                ,scope: this
                ,handler: this.onDeleteClick
            }
            ,permissions: {
                id: 'permissions' + this.instanceId
                ,text: L.Permissions
                ,scope: this
                ,handler: this.onPermissionsClick
            }
            ,addtask: {
                text: L.AddTask
                ,data: {
                    template_id: App.config.default_task_template
                }
                ,scope: this
                ,handler: this.onCreateObjectClick
            }
            ,completetask: {
                iconCls: 'ib-task-complete'
                ,id: 'completetask' + this.instanceId
                ,scale: 'large'
                ,text: L.Done
                ,scope: this
                ,handler: this.onCompleteTaskClick
            }
            ,closetask: {
                text: L.ClosingTask
                ,scope: this
                ,handler: this.onCloseTaskClick
            }
            ,reopentask: {
                text: L.ReopeningTask
                ,scope: this
                ,handler: this.onReopenTaskClick
            }
            ,subscribe: {
                text: L.Subscribe
                ,scope: this
                ,handler: this.onSubscribeClick
            }
            ,unsubscribe: {
                text: L.Unsubscribe
                ,scope: this
                ,handler: this.onUnsubscribeClick
            }
            // ,attachfile: {
            //     text: L.AttachFile
            //     ,scope: this
            //     ,handler: this.onAttachFileClick
            // }
            ,metadata: {
                text: L.Metadata
                ,id: 'metadata' + this.instanceId
                ,scope: this
                ,handler: this.onMetadataClick
            }
            ,webdavlink: {
                text: 'WebDAV Link'
                ,id: 'webdavlink' + this.instanceId
                ,scope: this
                ,handler: this.onWebDAVLinkClick
            }
            ,permalink: {
                text: 'Permalink'
                ,id: 'permalink' + this.instanceId
                ,scope: this
                ,handler: this.onPermalinkClick
            }
            ,'new': {
                text: L.New
                ,name: 'newmenu'
                ,menu: []
            }
        };

        /* will user BC abreviation for Button Collection */
        this.BC = new Ext.util.MixedCollection();

        this.BC.addAll([
            new Ext.Button(this.actions.back)
            ,new Ext.Button(this.actions.edit)
            ,new Ext.Button(this.actions.download)
            ,new Ext.Button(this.actions.search)
            ,new Ext.Button(this.actions.save)
            ,new Ext.Button(this.actions.cancel)
            ,new Ext.Button(this.actions.openInTabsheet)
            ,new Ext.Button(this.actions.fitImage)
            ,new Ext.Button(this.actions.completeTask)
            ,new Ext.Button(this.actions.preview)

            ,new Ext.Button({
                iconCls: 'ib-points'
                ,id: 'more' + this.instanceId
                ,scale: 'large'
                ,scope: this
                ,handler: function(b, e) {
                    this.updateCreateMenu();
                    this.menu.showBy(b.getEl());
                }
            })
        ]);

        Ext.apply(this, {
            hideMode: 'offsets'
            ,tbar: [
                this.BC.get('back' + this.instanceId)
                ,this.BC.get('search' + this.instanceId)
                ,this.BC.get('edit' + this.instanceId)
                ,this.BC.get('fitImage' + this.instanceId)
                ,this.BC.get('download' + this.instanceId)
                ,this.BC.get('save' + this.instanceId)
                ,this.BC.get('cancel' + this.instanceId)
                ,this.BC.get('completetask' + this.instanceId)
                ,'->'
                ,this.BC.get('preview' + this.instanceId)
                ,this.BC.get('openInTabsheet' + this.instanceId)
                ,this.BC.get('more' + this.instanceId)
            ]
            ,defaults: {
                border: false
                ,header: false
            }
            ,items: [{
                    title: L.Properties
                    ,iconCls: 'icon-infoView'
                    ,xtype: 'CBObjectProperties'
                    ,api: CB_Objects.getPluginsData
                    ,listeners: {
                        scope: this
                        ,openpreview: this.onOpenPreviewEvent
                        ,openproperties: this.onOpenPropertiesEvent
                        ,editobject: this.onEditObjectEvent
                        ,editmeta: this.onEditMetaEvent
                        ,loaded: this.onCardItemLoaded
                    }
                },{
                    title: L.Edit
                    ,iconCls: 'icon-edit'
                    ,xtype: 'CBEditObject'
                    ,listeners: {
                        scope: this
                        ,change: function(){
                            var ef = this.items.getAt(1);
                            this.actions.save.setDisabled(!ef.isValid() || !ef._isDirty);
                        }
                        ,clear: function(){
                            var ef = this.items.getAt(1);
                            this.actions.save.setDisabled(!ef.isValid() || !ef._isDirty);
                        }
                        ,loaded: this.onCardItemLoaded
                    }
                },{
                    title: L.Preview
                    ,iconCls: 'icon-preview'
                    ,xtype: 'CBObjectPreview'
                    ,listeners: {
                        scope: this
                        ,loaded: this.onCardItemLoaded
                    }
                }
            ]
            ,listeners: {
                scope: this
                ,add: this.onCardItemAdd
                ,lockpanel: this.onLockPanelEvent
                ,saveobject: this.onSaveObjectEvent
                ,beforedestroy: this.onBeforeDestroy
            }
        });


        CB.ObjectCardView.superclass.initComponent.apply(this, arguments);

        this.topToolbar = this.dockedItems.getAt(0);

        this.delayedLoadTask = new Ext.util.DelayedTask(this.doLoad, this);

        this.enableBubble(['changeparams', 'filedownload', 'createobject']);

        App.mainViewPort.on('objectsdeleted', this.onObjectsDeleted, this);
        App.on('objectchanged', this.onObjectChanged, this);
    }

    ,onBeforeDestroy: function(c) {
        App.mainViewPort.un('objectsdeleted', this.onObjectsDeleted, this);
    }

    ,getButton: function() {
        if(!this.button) {
            this.button = new Ext.SplitButton({
                iconCls: 'ib-app-view'
                ,scale: 'large'
                ,iconAlign:'top'
                ,enableToggle: true
                ,scope: this
                ,toggleHandler: this.onButtonToggle
                ,menu: []
            });
        }
        return this.button;
    }

    ,onButtonToggle: function(b, e){
        if(b.pressed){
            this.show();
            this.load(this.loadedData);
        }else{
            this.hide();
        }
    }

    ,onCardItemAdd: function(container, component, index){
        if(container !== this) {
            return;
        }
        var b = this.getButton();
        b.menu.add({
            text: component.title
            ,iconCls: component.iconCls
            ,scope: this
            ,handler: this.onViewChangeClick
        });
    }

    ,onViewChangeClick: function(buttonOrIndex, autoLoad){
        var currentItemIndex = this.items.indexOf(this.getLayout().activeItem);
        var mb = this.getButton();

        var idx = Ext.isNumber(buttonOrIndex)
            ? buttonOrIndex
            : mb.menu.items.indexOf(buttonOrIndex);

        if(currentItemIndex == idx) {
            return;
        }
        this.getLayout().activeItem.clear();

        this.getLayout().setActiveItem(idx);

        if(!mb.pressed) {
            mb.toggle();
        }

        this.onViewChange();

        if(autoLoad !== false) {
            this.load(this.requestedLoadData);
        }
    }

    ,onViewChange: function() {
        var d = this.loadedData;
        this.actions.edit.setDisabled(isNaN(d.id) || Ext.isEmpty(d.id));
        this.BC.get('preview' + this.instanceId).toggle(this.loadedData.viewIndex == 2, false);
    }

    /**
     * loading an object into the panel in a specific view
     * @param  {[type]} objectData
     * @return {[type]}
     */
    ,load: function(objectData) {
        if(this.locked) {
            delete this.requestedLoadData;
            return;
        }

        if(!isNaN(objectData)) {
            objectData = {
                id: objectData
            };
        }

        if(Ext.isEmpty(objectData.id) || isNaN(objectData.id)) {
            return;
        }

        var ai = this.getLayout().activeItem;

        //current view index
        var cvi = this.items.indexOf(ai);

        // check  if a new request is waiting to be loaded
        if(Ext.isEmpty(this.requestedLoadData)) {
            //check if object data are identical to previous loaded object
            if(this.loadedData && objectData &&
                (objectData.id == this.loadedData.id) &&
                (Ext.valueFrom(objectData.viewIndex, cvi) == Ext.valueFrom(this.loadedData.viewIndex, cvi))
            ) {
                return;
            }

            // save current croll position for history navigation
            if(!Ext.isEmpty(ai.body)) {
                this.loadedData.scroll = ai.body.getScroll();
            }
        } else {
            //check if object data are identical to previous load request
            if((objectData.id == this.requestedLoadData.id) &&
                (Ext.valueFrom(objectData.viewIndex, cvi) == Ext.valueFrom(this.requestedLoadData.viewIndex, cvi))
            ) {
                return;
            }
        }

        // cancel previous wating request and start a new one
        this.delayedLoadTask.cancel();

        // save requested data
        this.requestedLoadData = Ext.apply({}, objectData);

        //check if we are not in edit mode
        if(this.getLayout().activeItem.getXType() !== 'CBEditObject') {

            //automatic switch to plugins panel if different object types
            if(CB.DB.templates.getType(this.requestedLoadData.template_id) !=
                CB.DB.templates.getType(this.loadedData.template_id)
            ) {
                this.onViewChangeClick(0);
            }

            this.items.getAt(0).clear();

            // instantiate a delay to exclude flood requests
            this.delayedLoadTask.delay(60, this.doLoad, this);
        }
    }

    ,doLoad: function() {
        if(this.locked) {
            delete this.requestedLoadData;
            return;
        }

        // var err = new Error();

        var id = this.requestedLoadData
            ? Ext.valueFrom(this.requestedLoadData.nid, this.requestedLoadData.id)
            : null;
        this.addParamsToHistory(this.loadedData);

        this.loadedData = Ext.apply({id: id}, this.requestedLoadData);

        if(Ext.isDefined(this.loadedData.viewIndex)) {
            this.onViewChangeClick(this.loadedData.viewIndex, false);
        }

        delete this.requestedLoadData;

        var activeItem = this.getLayout().activeItem;

        this.loadedData.viewIndex = this.items.indexOf(activeItem);
        switch(activeItem.getXType()) {
            case 'CBObjectPreview':
                this.topToolbar.setVisible(!Ext.isEmpty(id));
                this.doLayout();

                //used params by preview component to detect wich buttons to display when asked
                activeItem.params = this.loadedData;

                activeItem.loadPreview(id);
                break;
            case 'CBObjectProperties':
            case 'CBEditObject':
                activeItem.load(this.loadedData);
                break;
        }
        this.onViewChange();
    }

    ,onCardItemLoaded: function(item) {
        this.locked = false;

        this.updateToolbarAndMenuItems();

        this.fireEvent('loaded', this, item);

        if(Ext.isEmpty(this.loadedData) || Ext.isEmpty(this.loadedData.scroll)) {
            return;
        }
        if(item.body) {
            item.body.scrollTo('left', this.loadedData.scroll.left);
            item.body.scrollTo('top', this.loadedData.scroll.top);
        }
    }

    ,updateToolbarAndMenuItems: function() {
        var ai = this.getLayout().activeItem;
        var ti = ai.getContainerToolbarItems();

        if(this.menu) {
            this.menu.removeAll(true);
            this.menu.destroy();
        }
        this.menu = new Ext.menu.Menu({items:[]});

        if(Ext.isEmpty(ti)) {
            return;
        }

        this.topToolbar.items.each(
            function(i) {
                if((i.id != ('back'+ this.instanceId)) && (i.getXType() !== 'tbfill')) {
                    i.hide();
                }
            }
        );

        /* update menu items */
        var isFirstItem = true;
        Ext.iterate(
            ti.menu
            ,function(k, v, o) {

                if(k == '-') {
                    this.menu.add('-');
                } else {
                    if ((!isFirstItem) &&
                      (v.addDivider == 'top')
                    ) {
                        this.menu.add('-');
                    }

                    var b = this.menuItemConfigs[k];
                    if(b) {
                        var cfg = Ext.apply({}, b);
                        var item = this.menu.add(cfg);
                        isFirstItem = false;
                    }
                }
            }
            ,this
        );

        //add "more" button to toolbar config if menu is not empty
        if(this.menu.items.getCount() > 0) {
            ti.tbar['more'] = {};
        }

        // add back button to config (always visible)
        if(!Ext.isDefined(ti.tbar['back'])) {
            ti.tbar['back'] = {};
        }

        // hide all bottons from toolbar
        Ext.iterate(
            ti.tbar
            ,function(k, v, o) {
                var b = this.BC.get(k + this.instanceId);
                //if not defined the we should add this custom button
                //to the collection to be available later
                if(b) {
                    if(b.baseAction) {
                        b.baseAction.show();
                    } else {
                        b.show();
                    }
                }
            }
            ,this
        );
    }

    ,updateCreateMenu: function() {
        if(!this.menu) {
            return;
        }

        var nmb = this.menu.child('[name="newmenu"]');

        if(nmb) {
            updateMenu(
                nmb
                ,this.getLayout().activeItem.createMenu
                ,this.onCreateObjectClick
                ,this
            );
            nmb.setDisabled(nmb.menu.items.getCount() < 1);
        }
    }

    ,addParamsToHistory: function(p) {
        var ai = this.getLayout().activeItem;
        //current view index
        var cvi = this.items.indexOf(ai);

        if((cvi == 1) || // edit view
            Ext.isEmpty(p) ||
            (Ext.encode(p) == '{}') ||
            (isNaN(p.id)) ||
            this.historyNavigation
        ) {
            delete this.historyNavigation;
            return;
        }
        this.history.push(Ext.apply({}, p));
        this.actions.back.setDisabled(false);
    }

    ,onBackClick: function() {
        if(Ext.isEmpty(this.history)) {
            this.actions.back.setDisabled(true);
            return;
        }
        this.delayedLoadTask.cancel();

        this.historyNavigation = true;
        this.requestedLoadData = this.history.pop();
        if(Ext.isEmpty(this.history)) {
            this.actions.back.setDisabled(true);
        }

        this.doLoad();
    }

    ,edit: function (objectData, e) {
        switch(detectFileEditor(objectData.name)) {
            case 'text':
            case 'html':
                App.mainViewPort.onFileOpen(objectData, e);
                break;
            case 'webdav':
                App.openWebdavDocument(objectData);
                break;
            default:
                this.editObject(objectData);
                break;
        }
    }

    ,editObject: function(objectData) {
        var data = Ext.apply({}, objectData);
        //edit object in popup window
        delete data.html;
        App.openObjectWindow(data);

        //edit object in current panel
        // data.viewIndex = 1;
        // this.delayedLoadTask.cancel();
        // this.requestedLoadData = data;
        // this.doLoad();
    }

    ,onEditClick: function() {
        var p = Ext.apply({}, this.loadedData);

        var templateCfg = CB.DB.templates.getProperty(p.template_id, 'cfg');
        if(templateCfg && (Ext.valueFrom(templateCfg.editMethod, templateCfg.createMethod) == 'tabsheet')) {
                App.mainViewPort.openObject(p);
        } else {
            this.edit(p);
        }
    }

    ,onReloadClick: function() {
        this.getLayout().activeItem.reload();
    }

    ,onDeleteClick: function() {
        if(this.loadedData && !isNaN(this.loadedData.id)) {
            App.mainViewPort.fireEvent('deleteobject', this.loadedData);
        }
    }

    ,onPermissionsClick: function(b, e) {
        App.mainViewPort.openPermissions(this.loadedData.id);
    }

    /**
     * actions to be made on objects deletion
     *
     * There is no need to reload this view because the grid will reload and change the selection,
     * but need to cancel the edit
     *
     * @param  array ids
     * @param  object e
     * @return void
     */
    ,onObjectsDeleted: function(ids, e) {
        if(!Ext.isEmpty(this.loadedData) && setsHaveIntersection(ids, this.loadedData.id)) {
            delete this.locked;
            this.onViewChangeClick(0, false);
            this.items.getAt(0).clear();
            this.updateToolbarAndMenuItems();
        }
    }

    ,onSearchClick: function() {
        var p = Ext.copyTo({}, this.loadedData, 'id,template_id');
        var ai = this.getLayout().activeItem;
        if(ai.readValues) {
            p.data = ai.readValues().data;
        }

        this.fireEvent('changeparams', {search: p});
    }

    ,onSaveClick: function() {
        this.getLayout().activeItem.save(
            //callback function
            function(component, form, action){
                var id = Ext.valueFrom(action.result.data.id, this.loadedData.id);
                var name = Ext.valueFrom(action.result.data.name, this.loadedData.name);

                var p = Ext.apply({}, this.loadedData);
                p.id = id;
                p.name = name;

                if(this.goBackOnSave) {
                    this.onBackClick();

                } else {
                    p.viewIndex = 0;
                    this.requestedLoadData = p;

                    this.doLoad();
                    // this.skipNextPreviewLoadOnBrowserRefresh = true;
                }
                delete this.goBackOnSave;
            }
            ,this
        );
    }

    ,onSaveObjectEvent: function(objComp, ev) {
        ev.stopPropagation();
        ev.preventDefault();
        if(this.actions.save.isDisabled()) {
            return false;
        }
        this.onSaveClick();
    }

    ,onCancelClick: function() {
        if(isNaN(this.loadedData.id) && !Ext.isEmpty(this.history)) {
            this.onBackClick();

        } else {
            var p = Ext.apply({}, this.loadedData);
            p.viewIndex = 0;
            this.requestedLoadData = p;

            this.doLoad();
        }
        delete this.goBackOnSave;
    }

    /**
     * toggle fit image preview
     *
     * This method actually should be managed by preview component
     *
     * @param  object b button
     * @param  object e event
     * @return void
     */
    ,onFitImageClick: function(b, e) {
        var ai = this.getLayout().activeItem;
        if(ai.onFitImageClick) {
            ai.onFitImageClick(b, e);
        }
    }

    ,onPreviewClick: function(b, e) {
        if(b.pressed) {
            this.onOpenPreviewEvent(this.loadedData, e);
        } else { //load Properties
            var p = Ext.apply({}, this.loadedData);
            p.viewIndex = 0;
            this.delayedLoadTask.cancel();
            this.requestedLoadData = p;

            this.doLoad();
        }
    }

    ,onOpenInTabsheetClick: function(b, e) {
        var ai = this.getLayout().activeItem;
        var cai = this.items.indexOf(ai);
        var d = Ext.apply({}, this.loadedData);

        if(ai.getXType() == 'CBEditObject') {
            d = Ext.apply({}, ai.data);
            if(ai.readValues) {
                d = Ext.apply(d, ai.readValues());
            }
        }

        if(cai > 0) {
            ai.clear();
            this.loadedData = {};
            this.onViewChangeClick(0, false);//
            this.onCardItemLoaded(ai);
        }

        switch(CB.DB.templates.getType(d.template_id)) {
            case 'file':
                App.mainViewPort.onFileOpen(d, e);
                break;
            default:
                App.mainViewPort.openObject(d, e);
        }
    }

    ,onOpenPreviewEvent: function(data, ev) {
        if(Ext.isEmpty(data)) {
            data = this.loadedData;
        }
        var p = Ext.apply({}, data);
        p.viewIndex = 2;
        this.delayedLoadTask.cancel();
        this.requestedLoadData = p;

        this.doLoad();
    }

    ,onOpenPropertiesEvent: function(data, sourceCmp, ev) {
        if(Ext.isEmpty(data)) {
            data = this.loadedData;
        }

        this.load(data);
    }

    ,onEditObjectEvent: function(params, e) {
        if(e) {
            e.stopPropagation();
        }
        this.onEditClick();
    }

    ,onEditMetaEvent: function(params, e) {
        if(e) {
            e.stopPropagation();
        }

        this.editObject(params);
    }

    ,onDownloadClick: function(b, e) {
        this.fireEvent('filedownload', [this.loadedData.id], false, e);
    }

    ,onCreateObjectClick: function(b, e) {
        this.goBackOnSave = true;

        var d = b.config.data;
        d.pid = this.loadedData.id;
        d.path = this.loadedData.path;
        this.fireEvent('createobject', d, e);
    }

    ,onCloseTaskClick: function(b, e) {
        this.getEl().mask(L.CompletingTask + ' ...', 'x-mask-loading');
        CB_Tasks.close(this.loadedData.id, this.onTaskChanged, this);
    }

    ,onReopenTaskClick: function(b, e) {
        this.getEl().mask(L.ReopeningTask + ' ...', 'x-mask-loading');
        CB_Tasks.reopen(this.loadedData.id, this.onTaskChanged, this);
    }

    ,onCompleteTaskClick: function(b, e) {
        CB_Tasks.complete(
            {
                id: this.loadedData.id
                ,message: ''
            }
            ,this.onTaskChanged
            ,this
        );
    }

    ,onTaskChanged: function(r, e){
        this.getEl().unmask();
        App.fireEvent('objectchanged', this.loadedData, this);
    }

    ,onSubscribeClick: function(b, e) {
        Ext.Msg.show({
            title: L.Subscribe
            ,msg: L.SubscribeMsg
            ,width: 300
            ,buttons: {
                yes: L.Subscribe
                ,no: L.SubscribeRecursive
                ,cancel: Ext.Msg.buttonText.cancel
            }
            ,scope: this
            ,fn: function(b, t) {
                if(b !== 'cancel') {
                    CB_Browser.subscribe(
                        {
                            id: this.loadedData.id
                            ,recursive: (b == 'no')
                        }
                        ,this.onSubscribeProcess
                        ,this
                    );
                }
            }
            ,icon: Ext.MessageBox.QUESTION
        });
    }

    ,onSubscribeProcess: function (r, e) {
        if(r.success !== true) {
            return;
        }

        this.onReloadClick();
    }

    ,onUnsubscribeClick: function () {
        CB_Browser.unsubscribe(
            {
                id: this.loadedData.id
            }
            ,this.onSubscribeProcess
            ,this
        );
    }

    // ,onAttachFileClick: function(b, e) {
    //     this.onViewChangeClick(0);
    //     var fp = this.down('CBObjectsPluginsFiles');

    //     if(Ext.isEmpty(fp)) {
    //         return;
    //     }
    //     fp.show();
    //     fp.onAddClick(b, e);
    // }

    ,onMetadataClick: function(b, e) {
        this.onEditMetaEvent(this.loadedData);
    }

    ,onWebDAVLinkClick: function(b, e) {
        App.openWebdavDocument(
            this.loadedData
            ,false
        );
    }

    ,onPermalinkClick: function(b, e) {
        window.prompt(
            'Copy to clipboard: Ctrl+C, Enter'
            , window.location.origin + '/' + App.config.coreName + '/v-' + this.loadedData.id + '/');
    }

    ,onLockPanelEvent: function(status) {
        this.locked = status;
    }

    ,setSelectedVersion: function(params) {
        var ai = this.getLayout().activeItem;
        if(ai.isXType('CBObjectProperties')) {
            ai.setSelectedVersion(params);
        }
    }

    ,onObjectChanged: function(data, component) {
        if(!isNaN(data)) {
            data = {id: data};
        }

        if(!Ext.isEmpty(data.isNew)) {
            this.requestedLoadData = data;
            this.doLoad();
            return;
        }

        if(!Ext.isEmpty(this.loadedData)) {
            if((data.pid == this.loadedData.id) || (data.id == this.loadedData.id)) {
                this.onReloadClick();
            }
        }
    }
}
);
