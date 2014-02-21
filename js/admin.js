/* Copyright (c) 2009 Arnaud Renevier, Inc, published under the modified BSD
 * license. */

/*
 * Fix canvas rendering engine race condition. See js/syp.js for more explanation.
 */
OpenLayers.Renderer.Canvas.prototype = OpenLayers.Util.extend({
    needsRedraw: false,
    imagesLoading: 0,
}, OpenLayers.Renderer.Canvas.prototype);
OpenLayers.Renderer.Canvas.prototype.oldRedraw = OpenLayers.Renderer.Canvas.prototype.redraw;
OpenLayers.Renderer.Canvas.prototype.redraw = function() {
    if (this.imagesLoading > 0) {
        this.needsRedraw = true;
        return;
    }
    OpenLayers.Renderer.Canvas.prototype.oldRedraw.apply(this, arguments);
}
OpenLayers.Renderer.Canvas.prototype.drawExternalGraphic = function(pt, style) {
    var img = new Image();
    img.src = style.externalGraphic;
       
    if(style.graphicTitle) {
        img.title=style.graphicTitle;           
    }

    var width = style.graphicWidth || style.graphicHeight;
    var height = style.graphicHeight || style.graphicWidth;
    width = width ? width : style.pointRadius*2;
    height = height ? height : style.pointRadius*2;
    var xOffset = (style.graphicXOffset != undefined) ?
        style.graphicXOffset : -(0.5 * width);
   var yOffset = (style.graphicYOffset != undefined) ?
       style.graphicYOffset : -(0.5 * height);
   var opacity = style.graphicOpacity || style.fillOpacity;
       
   var context = { img: img, 
                   x: (pt[0]+xOffset), 
                   y: (pt[1]+yOffset), 
                   width: width, 
                   height: height, 
                   canvas: this.canvas };

   var self = this;
   this.imagesLoading++;
   img.onerror = function() {
       self.imagesLoading--;
       if ((self.imagesLoading == 0) && (self.needsRedraw)) {
           self.needsRedraw = false;
           self.redraw();
       }
   }
   img.onload = OpenLayers.Function.bind( function() {
       self.imagesLoading--;
       if ((self.imagesLoading == 0) && (self.needsRedraw)) {
           self.needsRedraw = false;
           self.redraw();
       } else {
            this.canvas.drawImage(this.img, this.x, 
                             this.y, this.width, this.height);
       }
   }, context);   
}
// drag feature with tolerance
OpenLayers.Control.SypDragFeature = OpenLayers.Class (OpenLayers.Control.DragFeature, {
    startPixel: null,
    dragStart: null,
    pixelTolerance : 0,
    timeTolerance: 300,

    downFeature: function(pixel) {
        OpenLayers.Control.DragFeature.prototype.downFeature.apply(this, arguments);
        this.dragStart = (new Date()).getTime(); 
        this.startPixel = pixel; 
    },

    doneDragging: function(pixel) {
        OpenLayers.Control.DragFeature.prototype.doneDragging.apply(this, arguments);
        // Check tolerance. 
        var passesTimeTolerance =  
                    (new Date()).getTime() > this.dragStart + this.timeTolerance; 

        var xDiff = this.startPixel.x - pixel.x; 
        var yDiff = this.startPixel.y - pixel.y; 

        var passesPixelTolerance =  
        Math.sqrt(Math.pow(xDiff,2) + Math.pow(yDiff,2)) > this.pixelTolerance; 

        if(passesTimeTolerance && passesPixelTolerance){ 
            this.onComplete(this.feature, pixel);    
        } else { 
            var feature = this.feature; 
            var res = this.map.getResolution(); 
            this.feature.geometry.move(res * (this.startPixel.x - this.lastPixel.x), 
                    res * (this.lastPixel.y - this.startPixel.y)); 
            this.layer.drawFeature(this.feature); 
        }
        this.layer.drawFeature(this.feature, "select");
    },

    moveFeature: function(pixel) {
        OpenLayers.Control.DragFeature.prototype.moveFeature.apply(this, arguments);
        this.layer.drawFeature(this.feature, "temporary");
    },

    overFeature: function (feature) {
        // can only drag and drop currently selected feature
        if (feature != Admin.currentFeature) {
            return;
        }
        OpenLayers.Control.DragFeature.prototype.overFeature.apply(this, arguments);
    },

    CLASS_NAME: "OpenLayers.Control.SypDragFeature"
});

var Admin = {
    Markers: {
        ICON: "media/marker-normal.png",
        SELECT_ICON: "media/marker-selected.png",
        TEMPORARY_ICON: "media/marker-temp.png",
        HEIGHT: 25
    },

    map: null,
    baseLayer: null,
    dataLayer: null,
    selFeatureControl: null,
    moveFeatureControl: null,
    addFeatureControl: null,

    currentFeature: null,
    currentFeatureLocation: null,

    init: function () {
        this.map = new OpenLayers.Map ("map", {
                controls:[
                    new OpenLayers.Control.Navigation (),
                    new OpenLayers.Control.PanZoom ()
                ],
                projection: new OpenLayers.Projection("EPSG:900913"),
                displayProjection: new OpenLayers.Projection("EPSG:4326")
         });

         this.baseLayer = this.createBaseLayer ();
         this.map.addLayer(this.baseLayer);

         this.map.setCenter(new OpenLayers.LonLat(0, 0), 0);
         if (sypSettings.loggedUser) {
            this.dataLayer = this.createDataLayer (sypSettings.loggedUser);
            this.map.addLayer(this.dataLayer);
            this.reset();
         }
    },

    reset: function() {
        this.addFeatureControl.deactivate();
        this.moveFeatureControl.deactivate();
        this.selFeatureControl.activate();
        this.checkForFeatures();
        $("#newfeature_button").show().val(SypStrings.AddItem);
        $("#newfeature_button").unbind("click").click(function () {
            Admin.addNewFeature();
        });
    },

    createBaseLayer: function () {
        return new OpenLayers.Layer.OSM("OSM");
    },

    createDataLayer: function (user) {
        var styleMap = new OpenLayers.StyleMap (
                        {"default": {
                             externalGraphic: this.Markers.ICON,
                             graphicHeight: this.Markers.HEIGHT || 32 
                                },
                         "temporary": { 
                             externalGraphic: this.Markers.TEMPORARY_ICON,
                             graphicHeight: this.Markers.HEIGHT || 32 
                         },
                         "select": { 
                             externalGraphic: this.Markers.SELECT_ICON,
                             graphicHeight: this.Markers.HEIGHT || 32 
                    }});

        var layer = new OpenLayers.Layer.GML("KML", "items.php?from_user=" + encodeURIComponent(user),
           {
            styleMap: styleMap,
            format: OpenLayers.Format.KML, 
            projection: this.map.displayProjection,
            eventListeners: { scope: this,
                loadend: this.dataLayerEndLoad
            }
       });

        // controls
        this.selFeatureControl = this.createSelectFeatureControl(layer)
        this.map.addControl(this.selFeatureControl);
        this.moveFeatureControl = this.createMoveFeatureControl(layer)
        this.map.addControl(this.moveFeatureControl);
        this.addFeatureControl = this.createNewfeatureControl();
        this.map.addControl(this.addFeatureControl);

        return layer;
    },

    createMoveFeatureControl: function (layer) {
        var control = new OpenLayers.Control.SypDragFeature(
                layer, {
                         });
        return control;
    },

    createSelectFeatureControl: function (layer) {
        var control = new OpenLayers.Control.SelectFeature(
                layer, {
                        onSelect: OpenLayers.Function.bind(this.onFeatureSelect, this)
                         });
        return control;
    },

    createNewfeatureControl: function () {
        var control = new OpenLayers.Control ();
        var handler = new OpenLayers.Handler.Click(control, {
                'click': OpenLayers.Function.bind(FeatureMgr.add, FeatureMgr)
            });
        control.handler = handler;
        return control;
    },

    onFeatureSelect: function (feature) {
        this.showEditor(feature);
        FeatureMgr.reset();
        this.selFeatureControl.deactivate();
        this.moveFeatureControl.activate();
    },

    closeEditor: function() {
        if ($("#editor").css("display") == "none") {
            return;
        }
        if (this.currentFeature && this.currentFeature.layer) {
            this.selFeatureControl.unselect(this.currentFeature);
        }
        this.currentFeature = null;
        this.currentFeatureLocation = null;
        $("#img").removeAttr('src');
        $("#img").parent().html($("#img").parent().html());
        $("#img").parent().show();
        $("#title, #description").val("");
        $("#editor").hide();
        // do it once before hidding and once after hidding to work in all cases
        $("#title, #description").val(""); 
        $("#image_file").parent().html($("#image_file").parent().html());
        $(document).unbind("keydown");
        this.checkForFeatures();
        this.reset();
    },

    showEditor: function (feature) {
        $("#newfeature_button").hide();
        userMgr.close();

        if (feature.fid) {
            $("#delete").show();
        } else {
            $("#delete").hide();
        }
        $(document).unbind("keydown").keydown(function(e) { 
            if (e.keyCode == 27) {
                Admin.cancelCurrentFeature()
                e.preventDefault();
            }
        });
        this.currentFeature = feature;
        this.currentFeatureLocation = new OpenLayers.Pixel(feature.geometry.x, feature.geometry.y);
        $("#editor").show();
        $("#instructions").text(SypStrings.DragDropHowto);
        $("#title").val(feature.attributes.name);
        var fullDesc = $(feature.attributes.description).parent();
        $("#description").val(fullDesc.find('p').text());
        var src = fullDesc.find('img').attr('src');
        if (src) {
            $("#img").parent().show();
            $("#img").attr('src', src);
            $("#image_file").parent().hide();
            $("#image_delete").show();
        } else {
            $("#img").parent().hide();
            $("#image_file").parent().show();
            $("#image_delete").hide();
        }
        $("#title").select().focus(); 
    },

    dataLayerEndLoad: function() {
        // only set zoom extent once
        this.dataLayer.events.unregister('loadend', this, this.dataLayerEndLoad);
        this.dataLayer.events.register('loadend', this, this.checkForFeatures);

        if (!this.checkForFeatures()) {
            return;
        }

        var map = this.map;
        var orig = this.Utils.mbr (this.dataLayer);
        var centerBounds = new OpenLayers.Bounds();

        var mapProj = map.getProjectionObject();
        var sypOrigProj = new OpenLayers.Projection("EPSG:4326");

        var bottomLeft = new OpenLayers.LonLat(orig[0],orig[1]);
        bottomLeft = bottomLeft.transform(sypOrigProj, mapProj);
        var topRight = new OpenLayers.LonLat(orig[2],orig[3])
        topRight = topRight.transform(sypOrigProj, mapProj);

        centerBounds.extend(bottomLeft);
        centerBounds.extend(topRight);
        map.zoomToExtent(centerBounds);
    },

    checkForFeatures: function () {
        var features = this.dataLayer.features;
        if (features.length != 0) {
            $("#instructions").text(SypStrings.SelectHowto);
        }
        return !!features.length;
    },

    addNewFeature: function () {
        userMgr.close();

        function cancel() {
            $(document).unbind("keydown");
            Admin.reset()
        }
        $(document).unbind("keydown").keydown(function(e) { 
            if (e.keyCode == 27) {
                e.preventDefault();
                cancel();
            }
        });

        $("#newfeature_button").val(SypStrings.Cancel);
        $("#newfeature_button").unbind("click").click(cancel);

        $("#instructions").text(SypStrings.AddHowto);
        this.selFeatureControl.deactivate();
        this.addFeatureControl.activate();
        FeatureMgr.reset();
    },

    cancelCurrentFeature: function() {
        if (AjaxMgr.running) {
            return false;
        }
        var feature = this.currentFeature;
        if (feature) {
            if (feature.fid) {
                FeatureMgr.move (feature, this.currentFeatureLocation);
            } else {
                this.dataLayer.removeFeatures([feature]);
            }
        }
        this.closeEditor();
        return true;
    },

    reloadLayer: function (layer) {
        layer.destroyFeatures();
        layer.loaded = false;
        layer.loadGML();
    },

    Utils: {
        /* minimum bounds rectangle containing all feature locations.
         * FIXME: if two features are close, but separated by 180th meridian,
         * their mbr will span the whole earth. Actually, 179° lon and -170°
         * lon are considerated very near.
         */
        mbr: function (layer) {
            var features = [];
            var map = layer.map;

            var mapProj = map.getProjectionObject();
            var sypOrigProj = new OpenLayers.Projection("EPSG:4326");

            for (var i =0; i < layer.features.length; i++) {
                if (layer.features[i].cluster) {
                    features = features.concat(layer.features[i].cluster);
                } else {
                    features = features.concat(layer.features);
                }
            }

            var minlon = 180;
            var minlat = 88;
            var maxlon = -180;
            var maxlat = -88;

            if (features.length == 0) {
                // keep default values
            } else if (features.length == 1) {
                // in case there's only one feature, we show an area of at least 
                // 4 x 4 degrees
                var pos = features[0].geometry.getBounds().getCenterLonLat().clone();
                var lonlat = pos.transform(mapProj, sypOrigProj);

                minlon = Math.max (lonlat.lon - 2, -180);
                maxlon = Math.min (lonlat.lon + 2, 180);
                minlat = Math.max (lonlat.lat - 2, -90);
                maxlat = Math.min (lonlat.lat + 2, 90);
            } else {
                for (var i = 0; i < features.length; i++) {
                    var pos = features[i].geometry.getBounds().getCenterLonLat().clone();
                    var lonlat = pos.transform(mapProj, sypOrigProj);
                    minlon = Math.min (lonlat.lon, minlon);
                    minlat = Math.min (lonlat.lat, minlat);
                    maxlon = Math.max (lonlat.lon, maxlon);
                    maxlat = Math.max (lonlat.lat, maxlat);
                }
            }

            return [minlon, minlat, maxlon, maxlat];

        },


        escapeHTML: function (str) {
            if (!str) {
                return "";
            }
            return str.
             replace(/&/gm, '&amp;').
             replace(/'/gm, '&#39;').
             replace(/"/gm, '&quot;').
             replace(/>/gm, '&gt;').
             replace(/</gm, '&lt;');
        },

        startsWith: function (str, prefix) {
            return (str.slice(0, prefix.length) == prefix);
        },

        indexOf: function (array, item) {
            if (array.indexOf !== undefined) {
                return array.indexOf(item);
            } else {
                return OpenLayers.Util.indexOf(array, item);
            }
        }
    }
}

var FeatureMgr = {
    reset: function() {
        this.commError("");
    },

    add: function(evt) {
        var map = Admin.map;
        var pos = map.getLonLatFromViewPortPx(evt.xy);
        feature = this.update (null, pos, "", "", "");
        Admin.addFeatureControl.deactivate();
        Admin.selFeatureControl.select(feature);
    },

    move: function (feature, aLocation) {
        if (!feature || !aLocation) {
            return;
        }
        var curLoc = feature.geometry;
        feature.geometry.move(aLocation.x - curLoc.x, aLocation.y - curLoc.y);
        feature.layer.drawFeature(feature); 
    },

    update: function(feature, lonlat, imgurl, title, description) {
        var point = new OpenLayers.Geometry.Point(lonlat.lon, lonlat.lat);
        if (!feature) {
            feature = new OpenLayers.Feature.Vector(point);
            Admin.dataLayer.addFeatures([feature]);
        } else {
            this.move (feature, point);
        }
        feature.attributes.name = title;
        feature.attributes.description = "<p>" + Admin.Utils.escapeHTML(description) + "</p>"
                                + "<img src=\"" + imgurl + "\">"
        return feature;
    },

    del: function (feature) {
        var form = $("#feature_delete");
        form.find('input[name="fid"]').val(feature.fid);
        AjaxMgr.add({
            form: form,
            oncomplete: OpenLayers.Function.bind(this.ajaxReply, this),
            throbberid: "editor_throbber"
        });
    },

    save: function (feature) {
        var x = feature.geometry.x;
        var y = feature.geometry.y;

        var mapProj = feature.layer.map.getProjectionObject();
        var lonlat = new OpenLayers.LonLat(x, y).
                                    transform(mapProj,
                                              new OpenLayers.Projection("EPSG:4326"));
        var form = $("#feature_update");
        form.find('input[name="lon"]').val(lonlat.lon);
        form.find('input[name="lat"]').val(lonlat.lat);
        form.find('input[name="fid"]').val(feature.fid);
        form.find('input[name="keep_img"]').val(
            $("#img").attr("src") ? "yes": "no"
        );

        if (feature.fid) {
            form.find('input[name="request"]').val("update");
        } else {
            form.find('input[name="request"]').val("add");
        }
        AjaxMgr.add({
            form: form,
            oncomplete: OpenLayers.Function.bind(this.ajaxReply, this),
            throbberid: "editor_throbber"
        });
    },

    ajaxReply: function (data) {
        if (!data) {
            this.commError(SypStrings.ServerError);
            return;
        }

        var xml = new OpenLayers.Format.XML().read(data);
        if (!xml.documentElement) {
            this.commError(SypStrings.UnconsistentError);
            $("title").focus();
            return;
        }

        switch (xml.documentElement.nodeName.toLowerCase()) {
            case "error":
                switch (xml.documentElement.getAttribute("reason")) {
                    case "unauthorized":
                        pwdMgr.reset();
                        $("#cookie_warning").show();
                        this.reset();
                        Admin.cancelCurrentFeature();
                        Admin.reset();
                        userMgr.uninit();
                    break;
                    case "server":
                        this.commError(SypStrings.ServerError);
                        $("title").focus();
                    break;
                    case "unreferenced":
                        this.commError(SypStrings.UnreferencedError);
                        Admin.reloadLayer(Admin.dataLayer);
                        Admin.closeEditor();
                    break;
                    case "nochange":
                        this.commError(SypStrings.NochangeError);
                        Admin.closeEditor();
                    break;
                    case "request":
                        this.commError(SypStrings.RequestError);
                        $("title").focus();
                    break;
                    case "toobig":
                        this.commError(SypStrings.ToobigError);
                        $("#image_file").parent().html($("#image_file").parent().html());
                        $("#image_file").focus();
                    break;
                    case "notimage":
                        this.commError(SypStrings.NotimageError);
                        $("#image_file").parent().html($("#image_file").parent().html());
                        $("#image_file").focus();
                    break;
                    default:
                        this.commError(SypStrings.UnconsistentError);
                        $("title").focus();
                    break;
                }
            break;
            case "success":
                switch (xml.documentElement.getAttribute("request")) {
                    case "del":
                        this.commSuccess(SypStrings.DelSucces);
                        var someFeature = false;
                        var self = this;
                        $.each($(xml).find("FEATURE,feature"), function () {
                             someFeature = true;
                             var id = parseFloat($(this).find("ID:first,id:first").text());
                             if ((id === null) || isNaN (id)) {
                                return;;
                             }
                             var features = Admin.dataLayer.features;
                             for (var idx = 0; idx < features.length; idx++) {
                                 if (features[idx].fid == id) {
                                     Admin.dataLayer.removeFeatures([features[idx]]);
                                 }
                             }
                        });
                        if (someFeature == false) {
                            this.commError(SypStrings.UnconsistentError);
                        } else {
                            Admin.closeEditor();
                        }
                    break;
                    case "update":
                    case "add":
                        var someFeature = false;
                        var self = this;
                        $.each($(xml).find("FEATURE,feature"), function () {
                                someFeature = true;
                                var id = parseFloat($(this).find("ID:first,id:first").text());
                                if ((id === null) || isNaN (id)) {
                                    return;;
                                }

                                var lon = parseFloat($(this).find("LON:first,lon:first").text());
                                if ((typeof (lon) != "number") || isNaN (lon) ||
                                        (lon < -180) || (lon > 180)) {
                                    return;;
                                }

                                var lat = parseFloat($(this).find("LAT:first,lat:first").text());
                                if ((typeof (lat) != "number") || isNaN (lat) ||
                                        (lat < -90) || (lat > 90)) {
                                    return;;
                                }

                                var mapProj = Admin.map.getProjectionObject();
                                var lonlat = new OpenLayers.LonLat (lon, lat).
                                                transform( new OpenLayers.Projection("EPSG:4326"), mapProj);

                                var imgurl = $(this).find("IMGURL:first,imgurl:first").text();
                                var title = $(this).find("HEADING:first,heading:first").text();
                                var description = $(this).find("DESCRIPTION:first,description:first").text();

                                feature = self.update (Admin.currentFeature, lonlat, imgurl, title, description); 
                                feature.fid = id;
                        });

                        if (someFeature == false) {
                            this.commError(SypStrings.UnconsistentError);
                        } else {
                            this.commSuccess(SypStrings.UpdateSucces);
                            Admin.closeEditor();
                        }

                    break;
                    default:
                        this.commError(SypStrings.UnconsistentError);
                   break;
                }
            break;
            default:
                this.commError(SypStrings.UnconsistentError);
            break;
        }
    },

    commSuccess: function (message) {
        $("#server_comm").text(message);
        $("#server_comm").removeClass("error success").addClass("success");
    },

    commError: function (message) {
        $("#server_comm").text(message);
        $("#server_comm").removeClass("error success").addClass("error");
    }
}

/* maintains a queue of ajax queries, so I'm sure they all execute in the same
 * order they were defined */
var AjaxMgr = {
    _queue: [],

    running: false,

    add: function(query) {
        this._queue.push(query);
        if (this._queue.length > 1) {
            return;
        } else {
            this._runQuery(query);
        }
    },

    _runQuery: function(query) {
        var self = this;
        $('#api_frame').one("load", function() {
            self.running = false;
            self._reqEnd();
            if (query.throbberid) {
                $("#" + query.throbberid).css("visibility", "hidden");
            }
            if (typeof (query.oncomplete) == "function") {
                var body = null;
                try {
                    if (this.contentDocument) {
                        body = this.contentDocument.body;
                    } else if (this.contentWindow) {
                        body = this.contentWindow.document.body;
                    } else {
                        body = document.frames[this.id].document.body;
                    }
                } catch (e) {}
                    if (body) {
                        query.oncomplete(body.innerHTML);
                    } else {
                        query.oncomplete(null);
                    }
            }
        });
        query.form.attr("action", "api.php");
        query.form.attr("target", "api_frame");
        query.form.attr("method", "post");
        this.running = true;
        query.form.get(0).submit();
        if (query.throbberid) {
            $("#" + query.throbberid).css("visibility", "visible");
        }
        if (typeof (query.onsend) == "function") {
            query.onsend();
        }
    },

    _reqEnd: function() {
        this._queue.shift();
        if (this._queue.length > 0) {
            this._reqEnd(this._queue[0]);
        }
    }
}

var pwdMgr = {

    init: function () {
        $("#login_form").submit(this.submit);
        $("#user").focus().select();
    },

    reset: function() {
        this.commError ("");
        $("#login_area").show();
        $("#password").val("");
        $("#user").val(sypSettings.loggedUser).focus().select();
    },

    submit: function () {
        try {
            pwdMgr.commError("");
            var req = {
                form:  $("#login_form"),
                throbberid: "pwd_throbber",
                onsend: function() {
                    $("#login_error").hide();

                    // we need a timeout; otherwise those fields will not be submitted
                    window.setTimeout(function() { 
                            // removes focus from #password before disabling it. Otherwise, opera
                            // prevents re-focusing it after re-enabling it.
                            $("#user, #password").blur(); 
                            $("#login_submit, #user, #password").attr("disabled", "disabled");
                    }, 0)
                },
                oncomplete: OpenLayers.Function.bind(pwdMgr.ajaxReply, pwdMgr)
            };
            AjaxMgr.add(req);
        } catch(e) {}
        return false;
    },

    ajaxReply: function (data) {
        // here, we need a timeout because onsend timeout sometimes has not been triggered yet
        window.setTimeout(function() {
            $("#login_submit, #user, #password").removeAttr("disabled");
        }, 0);

        if (!data) {
            this.commError(SypStrings.ServerError);
            $("#login_error").show();
            window.setTimeout(function() {
                    $("#user").focus().select();
            }, 0);
            return;
        }

        var xml = new OpenLayers.Format.XML().read(data);
        if (!xml.documentElement) {
            this.commError(SypStrings.UnconsistentError);
            $("#login_error").show();
            window.setTimeout(function() {
                    $("#user").focus().select();
            }, 0);
        }

        switch (xml.documentElement.nodeName.toLowerCase()) {
            case "error":
                switch (xml.documentElement.getAttribute("reason")) {
                    case "server":
                        this.commError(SypStrings.ServerError);
                    break;
                    case "unauthorized":
                        this.commError(SypStrings.UnauthorizedError);
                    break;
                    case "request":
                        this.commError(SypStrings.RequestError);
                    break;
                    default:
                        this.commError(SypStrings.UnconsistentError);
                    break;
                }
                $("#login_error").show();
                window.setTimeout(function() {
                        $("#user").focus().select();
                }, 0);
            break;
            case "success":
                $("#login_area").hide();

                user = $(xml).find("USER,user").text();
                sypSettings.loggedUser = user;

                if (sypSettings.loggedUser == "admin")  {
                    userMgr.init();
                }

                if (Admin.selFeatureControl) {
                    Admin.selFeatureControl.destroy();
                }
                if (Admin.moveFeatureControl) {
                    Admin.moveFeatureControl.destroy();
                }
                if (Admin.addFeatureControl) {
                    Admin.addFeatureControl.destroy();
                }
                if (Admin.dataLayer) {
                    Admin.dataLayer.destroy();
                }

                Admin.dataLayer = Admin.createDataLayer(user);
                Admin.map.addLayer(Admin.dataLayer);
                Admin.reset();

            break;
            default:
                this.commError(SypStrings.UnconsistentError);
            break;
        }
    },

    commError: function (message) {
        $("#login_error").text(message);
        if (message) {
            $("#login_error").show();
        } else {
            $("#login_error").hide();
        }
    }
}

var userMgr = {
    _adduserDisplayed: false,
    _changepassDisplayed: false,

    init: function() {
        $("#user_close").unbind("click").click(function () {
            userMgr.close()
        });

        $("#change_pass").unbind("click").click(function() {
            userMgr.toggleChangePass();
            return false;
        });
        $("#changepass").unbind("submit").submit(function() {
            try {
                userMgr.changepass();
            } catch(e) {}
            return false;
        });

        if (sypSettings.loggedUser != "admin") {
            return;
        }

        $("#add_user").show();
        $("#add_user").unbind("click").click(function () {
            userMgr.toggleAddUser();
            return false;
        });
        $("#newuser").unbind("submit").submit(function() {
            try {
                userMgr.add();
            } catch(e) {}
            return false;
        });

    },

    disableForms: function() {
        $("#newuser_name, #newuser_password, #newuser_password_confirm, #newuser_submit").attr("disabled", "disabled");
        $("#pass_current, #pass_new, #pass_new_confirm, #pass_submit").attr("disabled", "disabled");
    },

    enableForms: function() {
        $("#newuser_name, #newuser_password, #newuser_password_confirm, #newuser_submit").removeAttr("disabled");
        $("#pass_current, #pass_new, #pass_new_confirm, #pass_submit").removeAttr("disabled");
    },

    resetForms: function() {
        $("#newuser_name, #newuser_password, #newuser_password_confirm").val("");
        $("#pass_current, #pass_new, #pass_new_confirm").val("");
    },

    uninit: function() {
        this.close();
        $("#add_user").unbind("click");
        $("#add_user").hide();
        $("#change_pass").unbind("click");
        $("#user_close").unbind("click");
        $("#newuser").unbind("submit");
        $("#changepass").unbind("submit");
    },

    close: function() {
        this.closeChangePass();
        this.closeAddUser();
    },

    toggleChangePass: function() {
        if (this._changepassDisplayed) {
            this.closeChangePass();
        } else {
            this.showChangePass();
        }
    },

    showChangePass: function() {
        if (!Admin.cancelCurrentFeature()) {
            return;
        }
        this.closeAddUser();

        $(document).unbind("keydown").keydown(function(e) { 
            if (e.keyCode == 27) {
                userMgr.closeChangePass()
                e.preventDefault();
            }
        });

        this.resetForms();
        this.enableForms();
        $("#user_area, #changepass").show();
        this.commError("");

        // XXX: setTimeout needed because otherwise, map becomes hidden in IE. Why ??
        window.setTimeout(function() { 
            $("#pass_current").focus();
        }, 0);

        this._changepassDisplayed = true;
    },

    closeChangePass: function() {
        if (!this._changepassDisplayed) {
            return;
        }
        $("#user_area, #changepass").hide();
        $(document).unbind("keydown");
        this._changepassDisplayed = false;
    },

    changepass: function() {
        var newpass = $("#pass_new").val();
        var newpass_confirm = $("#pass_new_confirm").val();
        if (newpass != newpass_confirm) {
            this.commError(SypStrings.userPasswordmatchError);
            $("#pass_new").focus().select();
            return;
        }

        if (!newpass) {
            this.commError(SypStrings.emptyPasswordError);
            $("#pass_new").focus().select();
            return;
        }

        var curpass = $("#pass_current").val();
        if (newpass == curpass) {
            this.commError(SypStrings.changeSamePass);
            $("#pass_new").focus().select();
            return;
        }

        this.commError("");

        AjaxMgr.add({
            form: $("#changepass"),
            oncomplete: OpenLayers.Function.bind(this.ajaxReply, this),
            throbberid: "user_throbber",
            onsend: function() { 
                // we need a timeout; otherwise those fields will not be submitted
                window.setTimeout(function() {
                    // removes focus from #password before disabling it. Otherwise, opera
                    // prevents re-focusing it after re-enabling it.
                    $("#pass_current, #pass_new, #pass_new_confirm").blur(); 
                    userMgr.disableForms();
                }, 0);
            }
        });
    },

    toggleAddUser: function() {
        if (this._adduserDisplayed) {
            this.closeAddUser();
        } else {
            this.showAddUser();
        }
    },

    showAddUser: function() {
        if (!Admin.cancelCurrentFeature()) {
            return;
        }

        this.closeChangePass();

        $(document).unbind("keydown").keydown(function(e) { 
            if (e.keyCode == 27) {
                userMgr.closeAddUser()
                e.preventDefault();
            }
        });

        $("#user_area, #newuser").show();
        this.resetForms();
        this.enableForms();
        this.commError("");

        // XXX: setTimeout needed because otherwise, map becomes hidden in IE. Why ??
        window.setTimeout(function() { 
            $("#newuser_name").focus();
        }, 0);

        this._adduserDisplayed = true;
    },

    closeAddUser: function() {
        if (!this._adduserDisplayed) {
            return;
        }
        $("#user_area, #newuser").hide();
        $(document).unbind("keydown");
        this._adduserDisplayed = false;
    },

    add: function() {
        var newuser_name = $("#newuser_name").val();
        if (!newuser_name) {
            this.commError(SypStrings.newUserNonameError);
            $("#newuser_name").focus();
            return;
        }

        var newuser_pass = $("#newuser_password").val();
        var newuser_pass_confirm = $("#newuser_password_confirm").val();
        if (newuser_pass != newuser_pass_confirm) {
            this.commError(SypStrings.userPasswordmatchError);
            $("#newuser_password").focus().select();
            return;
        }

        if (!newuser_pass) {
            this.commError(SypStrings.emptyPasswordError);
            $("#pass_new").focus().select();
            return;
        }

        this.commError("");

        AjaxMgr.add({
            form: $("#newuser"),
            oncomplete: OpenLayers.Function.bind(this.ajaxReply, this),
            throbberid: "user_throbber",
            onsend: function() { 
                // we need a timeout; otherwise those fields will not be submitted
                window.setTimeout(function() {
                    // removes focus from #password before disabling it. Otherwise, opera
                    // prevents re-focusing it after re-enabling it.
                    $("#newuser_name, #newuser_password, #newuser_password_confirm").blur(); 
                    userMgr.disableForms();
                }, 0);
            }
        });
    },

    ajaxReply: function (data) {
        if (!data) {
            // here, we need a timeout because onsend timeout sometimes has not been triggered yet
            var self = this;
            window.setTimeout(function() {
                self.enableForms();
             }, 0);
            this.commError(SypStrings.ServerError);
            return;
        }

        var xml = new OpenLayers.Format.XML().read(data);
        if (!xml.documentElement) {
            // here, we need a timeout because onsend timeout sometimes has not been triggered yet
            var self = this;
            window.setTimeout(function() {
                self.enableForms();
             }, 0);
            this.commError(SypStrings.UnconsistentError);
            return;
        }

        var needFormEnabling = true;
        var focusEl = null;

        switch (xml.documentElement.nodeName.toLowerCase()) {
            case "error":
                switch (xml.documentElement.getAttribute("reason")) {
                    case "unauthorized":
                        pwdMgr.reset();
                        $("#cookie_warning").show();
                        Admin.reset();
                        this.uninit();
                    break;
                    case "server":
                        this.commError(SypStrings.ServerError);
                        if (this._adduserDisplayed) {
                            focusEl = $("#newuser_name");
                        } else if (this._changepassDisplayed) {
                            focusEl = $("#pass_current");
                        }
                    break;
                    case "request":
                        this.commError(SypStrings.RequestError);
                        if (this._adduserDisplayed) {
                            focusEl = $("#newuser_name");
                        } else if (this._changepassDisplayed) {
                            focusEl = $("#pass_current");
                        }
                    break;
                    case "wrongpass":
                        this.commError(SypStrings.changePassBadPass);
                        focusEl = $("#pass_current");
                    break;
                    case "newuser_exists":
                        this.commError(SypStrings.newUserExistsError);
                        focusEl = $("#newuser_name");
                    break;
                    default:
                        this.commError(SypStrings.UnconsistentError);
                        if (this._adduserDisplayed) {
                            focusEl = $("#newuser_name");
                        } else if (this._changepassDisplayed) {
                            focusEl = $("#pass_current");
                        }
                    break;
                }
            break;
            case "success":
                switch (xml.documentElement.getAttribute("request")) {
                    case "newuser":
                        this.commSuccess(SypStrings.newUserSuccess);
                        needFormEnabling = false;
                    break;
                    case "changepass":
                        this.commSuccess(SypStrings.changePassSuccess);
                        needFormEnabling = false;
                    break;
                    default:
                        this.commError(SypStrings.UnconsistentError);
                        focusEl = $("newuser_name");
                    break;
                }
            break;
            default:
                this.commError(SypStrings.UnconsistentError);
                focusEl = $("newuser_name");
            break;
        }

        if (needFormEnabling) {
            // here, we need a timeout because onsend timeout sometimes has not been triggered yet
            var self = this;
            window.setTimeout(function() {
                self.enableForms();
                if (focusEl) {
                    focusEl.select().focus();
                }
             }, 0);
        } else {
            if (focusEl) {
                focusEl.focus().select();
            }
        }

    },

    commSuccess: function (message) {
        $("#user_comm").text(message);
        $("#user_comm").removeClass("error success").addClass("success");
    },

    commError: function (message) {
        $("#user_comm").text(message);
        $("#user_comm").removeClass("error success").addClass("error");
    }
}

$(window).load(function () {
    // if using .ready, ie triggers an error when trying to access
    // document.namespaces
    pwdMgr.init();
    $("#newfeature_button").click(function () {
        Admin.addNewFeature();
    });
    $("#editor_close").click(function () {
        Admin.cancelCurrentFeature()
    });
    $("#feature_update").submit(function() {
        try {
            FeatureMgr.save(Admin.currentFeature);
        } catch(e) {}
        return false;
    });
    $("#feature_delete").submit(function() {
        try {
            FeatureMgr.del(Admin.currentFeature);
        } catch(e) {}
        return false;
    });
    $("#image_delete").click(function() {
            $("#img").removeAttr('src');
            // needs to rebuild element otherwise some browsers still
            // display image.
            $("#img").parent().html($("#img").parent().html());
            $("#img").parent().hide();
            $("#image_delete").hide();
            $("#image_file").parent().show();
    });

    userMgr.init();
    Admin.init();
});
