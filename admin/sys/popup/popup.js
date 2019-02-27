
/* ###VERSIONSBLOCKINLCUDE### */

var JSpopup = Class.create({
  initialize: function (){
    this.flag = 0;
    this.prefix = "popup";
    this.defaults = {
      width: 600,
      height: 400,
      onClose: function (){},
      onOpen: function () {},
      onLoad: function () {},
      title: "Modal Window",
      opacity: 0.8,
      url: "/tpl/de/empty.htm",
      param: {},
      id: false
    };
    
    Object.extend(this.defaults, arguments[0] || { });
  },
  
  add: function (o) {
    if (o.target)
    {
      $(o.target).observe('click', (function() { this.open(o); }).bind(this));
      latest_popup = (function() { this.open(o); }).bind(this);
      //alert(o.target);
    }
  },
  
  open: function (){
    var options = Object.clone(this.defaults);
    options = Object.extend(options, arguments[0] || { });
    
    //events regestrieren
    this.onClose = options.onClose.methodize();
    this.onOpen = options.onOpen.methodize();
    this.onLoad = options.onLoad.methodize();
    
    if(this.onOpen)
      this.onOpen();

    this.startLoading(options);
//    alert(options.param);
    if ((typeof options.url != "undefined") && (options.url !== false)) {
      // Ajax
      new Ajax.Request(options.url, {
        method: "post",
        parameters: options.param,
        onComplete: (function (t){
          this.createPopup(options);
          this.insertResponse(Object.extend(options, {text: t.responseText} || { }));
          if(this.onLoad)
            this.onLoad();
        }).bind(this)
      });
    } else if (typeof options.content != "undefined") {
      this.createPopup(options);
      this.insertResponse(Object.extend(options, {text: options.content} || { }));
      if(this.onLoad)
        this.onLoad();
    }
    
  },
  
  startLoading: function (o){
    // Fügt neue Overlay hinzu falls noch keins vorhanden
    if(!$(this.prefix+"_overlay"))
      $$('body')[0].appendChild(new Element('div', { id:this.prefix+'_overlay', style:'z-index: 20' })).setOpacity(o.opacity);
    // erhöht zIndex +1
    $(this.prefix+"_overlay").style.zIndex = parseInt($(this.prefix+"_overlay").style.zIndex)+1;
    $$('body')[0].appendChild(new Element('div', {id:this.prefix+'_loader'}).insert("Loading..."));
    $(this.prefix+"_loader").style.zIndex = parseInt($(this.prefix+"_overlay").style.zIndex)+1;
  },
  
  createPopup: function (o){
    //alert(this.defaults.width);
    $(this.prefix+"_loader").remove();
    
    if(!$(this.prefix+'_container_'+o.id)) {
      //container
      $$('body')[0].appendChild(new Element('div', {id:this.prefix+'_container_'+o.id, 'class': this.prefix+'_container'}));
      $(this.prefix+"_container_"+o.id).setStyle({
        width: o.width+"px",
        height: o.height+"px",
        marginLeft: "-"+(Math.round(o.width/2))+"px",
        marginTop: "-"+(Math.round(o.height/2))+"px"
      });
      //zindex
      $(this.prefix+"_container_"+o.id).style.zIndex = parseInt($(this.prefix+"_overlay").style.zIndex);
      //$(this.prefix+"_overlay").style.zIndex = parseInt($(this.prefix+"_overlay").style.zIndex)-1;
      
      //title
      $(this.prefix+"_container_"+o.id).appendChild(new Element('div', {id:this.prefix+'_title_'+o.id, 'class':this.prefix+'_title'}).insert(o.title));
      var titleheight = $(this.prefix+"_title_"+o.id).getHeight();
    
      //close button
      $(this.prefix+"_title_"+o.id).appendChild(new Element('div', {id:this.prefix+'_close_'+o.id, 'class': this.prefix+'_close'}));
      $(this.prefix+"_close_"+o.id).observe('click', (function() { this.close(o); }).bind(this));
      
      //content
      $(this.prefix+"_container_"+o.id).appendChild(new Element('div', {id:this.prefix+'_content_'+o.id, 'class': this.prefix+'_content'}));
      $(this.prefix+"_content_"+o.id).style.height = o.height-titleheight+"px";
      
      this.flag++;
    }
    else
      $(this.prefix+"_overlay").style.zIndex = parseInt($(this.prefix+"_overlay").style.zIndex)-1;
  },
  
  insertResponse: function (o){
    $(this.prefix+"_content_"+o.id).update(o.text);
  },
  
  close: function (o){
    this.flag--;
//    alert(this.onClose);
    if(this.onClose) this.onClose();
    this.removeEvents(o);
    if(this.flag == 0) $(this.prefix+"_overlay").remove();
    else $(this.prefix+"_overlay").style.zIndex = parseInt($(this.prefix+"_overlay").style.zIndex)-1;
    $(this.prefix+"_container_"+o.id).remove();
    
  },
  
  removeEvents: function (o){
    //alert($(this.prefix+"_close_"+o.id));
    $(this.prefix+"_close_"+o.id).stopObserving('click', (function() { this.close(o); }).bind(this));
  }
});
var myPopup = new JSpopup();