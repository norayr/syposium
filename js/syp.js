/* Copyright (c) 2009 Arnaud Renevier, Inc, published under the modified BSD
 * license. */

/*
 * With canvas rendering engine, externalgraphics are drawn by loading and
 * Image javascript object and drawing it with drawImage once it has been
 * loaded. If matching feature is deleted while image is loading, redraw
 * function will be called before drawImage and therefore, feature is removed,
 * but image is still drawn on the screen. We fix it with locks: when an image is
 * loading, we defer redraw method.
 */
OpenLayers.Renderer.Canvas.prototype = OpenLayers.Util.extend({
    needsRedraw: false,
    imagesLoading: 0
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


OpenLayers.Control.SypAttribution = OpenLayers.Class (OpenLayers.Control.Attribution, {
    updateAttribution: function() {
        var attributions = [SypStrings.poweredByLink];
        if (this.map && this.map.layers) {
            for(var i=0, len=this.map.layers.length; i<len; i++) {
                var layer = this.map.layers[i];
                if (layer.attribution && layer.getVisibility()) {
                    attributions.push( layer.attribution );
                }
            }  
            this.div.innerHTML = attributions.join(this.separator);
        }
    }
});

var SYP = {
    Markers: {
        ICON: "media/marker-normal.png",
        SELECT_ICON: "media/marker-selected.png",
        HEIGHT: 25
    },

    map: null,
    baseLayer: null,
    dataLayer: null,
    selectControl: null,

    init: function() {
        this.map = new OpenLayers.Map("map", {
            controls:[
                new OpenLayers.Control.SypAttribution(),
                new OpenLayers.Control.Navigation(),
                new OpenLayers.Control.PanZoom(),
                new OpenLayers.Control.Permalink()
            ],
            projection: new OpenLayers.Projection("EPSG:900913"),
            displayProjection: new OpenLayers.Projection("EPSG:4326")
        } );

        this.baseLayer = this.createBaseLayer();
        this.dataLayer = this.createDataLayer();
        this.map.addLayers([this.baseLayer, this.dataLayer]);

        this.selectControl = this.createSelectControl();
        this.map.addControl(this.selectControl);
        this.selectControl.activate();

        if (!this.map.getCenter()) {
            this.map.setCenter(new OpenLayers.LonLat(0, 0), 0);
        }
    },

    createBaseLayer: function() {
        return new OpenLayers.Layer.OSM("OSM");
    },

    createDataLayer: function(map) {
        var defaultStyle = new OpenLayers.Style({
            externalGraphic: this.Markers.ICON,
            graphicHeight: "${height}",
            label: "${label}",
            fontColor: "white",
            fontWeight: "bold"
        }, {
            context: {
                height: function(feature) {
                    var defaultHeight = SYP.Markers.HEIGHT || 32;
                    var increase = 4 * (feature.attributes.count - 1);
                    return Math.min(defaultHeight + increase, 50);
                },
                label: function(feature) {
                    var renderer = feature.layer.renderer;
                    if (renderer.CLASS_NAME == "OpenLayers.Renderer.Canvas") {
                        return ""; // canvas backend cannot draw text above an external Image
                    }
                    return (feature.attributes.count > 1) ? feature.attributes.count: "";
                }
            }
        });
        var selectStyle = new OpenLayers.Style({
            externalGraphic: this.Markers.SELECT_ICON,
            graphicHeight: this.Markers.HEIGHT || 32 
        });
        var styleMap = new OpenLayers.StyleMap (
                        {"default": defaultStyle,
                         "select": selectStyle});

        var layer = new OpenLayers.Layer.GML("KML", "items.php", 
           {
               strategies: [
                new OpenLayers.Strategy.Cluster()
                ],
            styleMap: styleMap,
            format: OpenLayers.Format.KML, 
            projection: this.map.displayProjection,
            eventListeners: { scope: this,
                              loadend: this.dataLayerEndLoad
                            }
           });

        return layer;
    },

    createSelectControl: function() {
        var control = new OpenLayers.Control.SelectFeature(
                                            this.dataLayer, {
                                               onSelect: this.onFeatureSelect,
                                               onUnselect: this.onFeatureUnselect,
                                               toggle: true,
                                               clickout: false
                                                            });
        return control;
    },

    dataLayerEndLoad: function() {
        if (!this.checkForFeatures()) {
            return;
        }

        var map = this.map;
        if (map.getControlsByClass("OpenLayers.Control.ArgParser")[0].center
            == null) { // map center was not set in ArgParser control.
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
        }
    },

    checkForFeatures: function() {
        var features = this.dataLayer.features;
        if (features.length == 0) {
            var message = SypStrings.noImageRegistered;
            this.Utils.displayUserMessage(message, "warn");
        }
        return !!features.length;
    },

    createPopup: function(position, contentHTML) {
        var popup = new OpenLayers.Popup.Anchored("popup",
                                                  position,
                                                  null,
                                                  contentHTML,
                                                  null,
                                                  true);
        popup.autoSize = true;
        popup.backgroundColor = ""; // deal with it in css
        popup.border = ""; // deal with it in css
        popup.closeOnMove = true;
        return popup;
    },

    onFeatureUnselect: function (feature) {
        var map = feature.layer.map;
        var permaControl = map.getControlsByClass("OpenLayers.Control.Permalink");
        if (permaControl[0]) {
            permaControl[0].div.style.display = "";
        }
        if (!feature.popup) {
            this.map.events.unregister("movestart", this, this._unselect);
            return;
        }
        var popup = feature.popup;
        if (popup.visible()) {
            popup.hide();
        }
    },

    onZoomClusterEnd: function(arg) {
        var map = arg.object;
        var point = new OpenLayers.Geometry.Point(this.lonlat.lon, this.lonlat.lat);
        var center = map.getCenter();
        for (var i = this.layer.features.length; i-->0;) {
            var feature = this.layer.features[i];
            if (feature.geometry.equals(point) && 
                (feature.attributes.count == this.count)) {
                   var self = this;
                   window.setTimeout(function() { map.setCenter(self.lonlat, map.zoom + 1)}, 500);
                   return;
            }
        }
        SYP.selectControl.activate();
        map.events.unregister("zoomend", this, SYP.onZoomClusterEnd);
    },

    onFeatureSelect: function(feature) {
        var map = feature.layer.map;

        if (feature.attributes.count > 1) {
            this.unselect(feature);
            var lonlat = new OpenLayers.LonLat(feature.geometry.x, feature.geometry.y);
            var args = {
                lonlat: lonlat,
                layer: feature.layer,
                count: feature.attributes.count
            }
            map.events.register("zoomend", args, SYP.onZoomClusterEnd);
            SYP.selectControl.deactivate();
            map.setCenter(lonlat, map.zoom + 1);
            return;
        }
        var permaControl = map.getControlsByClass("OpenLayers.Control.Permalink");
        if (permaControl[0]) {
            permaControl[0].div.style.display = "none";
        }
        var popup = feature.popup;

        var popupPos = null;
        switch (sypSettings.popupPos) {
            case 0:
                popupPos = feature.geometry.getBounds().getCenterLonLat();
            break;
            case 1:
                popupPos = SYP.Utils.tlCorner(map, 8);
            break;
            case 2:
                popupPos = SYP.Utils.trCorner(map, 8);
            break;
            case 3:
                popupPos = SYP.Utils.brCorner(map, 8);
            break;
            case 4:
                popupPos = SYP.Utils.blCorner(map, 8);
            break;
            default:
                popupPos = SYP.Utils.brCorner(map, 8);
           break;
        }

        // we cannot reuse popup; we need to recreate it in order for IE
        // expressions to work. Otherwise, we get a 0x0 image on second view.
        if (popup) {
            popup.destroy();
        }
        var contentHTML;
        if (feature.cluster[0].attributes.name) {
            // escaping name is necessary because it's not enclosed in another html tag.
            contentHTML = "<h2>" +
                          SYP.Utils.escapeHTML(feature.cluster[0].attributes.name) +
                          "</h2>" + 
                          feature.cluster[0].attributes.description;
        } else {
            contentHTML = feature.cluster[0].attributes.description;
        }
        if (!contentHTML || !contentHTML.length) {
            this.map.events.register("movestart", this, this._unselect = function () { this.unselect(feature)});
            return;
        }
        popup = SYP.createPopup(popupPos, contentHTML);
        var control = this;
        popup.hide = function () {
            OpenLayers.Element.hide(this.div);
            control.unselectAll();
        };
        map.addPopup(popup);
        feature.popup = popup;
        var anchor = popup.div.getElementsByTagName("a")[0];
        if (anchor) {
            anchor.onclick = function() { 
                SYP.showBigImage(this.href);
                return false;
            }
        }
    },

    showBigImage: function (href) {
        if (OpenLayers.Util.getBrowserName() == "msie") {
            document.getElementById('bigimg_container').style.display = "block";
        } else {
            document.getElementById('bigimg_container').style.display = "table";
        }

        var maxHeight = document.body.clientHeight * 0.9;
        var maxWidth = document.body.clientWidth * 0.9;
        document.getElementById('bigimg').style.height = "";
        document.getElementById('bigimg').style.width = "";
        document.getElementById('bigimg').style.maxHeight = maxHeight + "px";
        document.getElementById('bigimg').style.maxWidth = maxWidth + "px";
        document.getElementById('bigimg').onload = function () {
            var heightRatio = this.clientHeight / parseInt(this.style.maxHeight);
            var widthRatio = this.clientWidth / parseInt(this.style.maxWidth);
            if (heightRatio > 1 || widthRatio > 1) {
                if (heightRatio > widthRatio) {
                    this.style.height = this.style.maxHeight;
                } else {
                    this.style.width = this.style.maxWidth;
                }
            }

            var offsetTop = this.offsetTop;
            var offsetLeft = this.offsetLeft;
            var par = this.offsetParent;
            var ismsie = OpenLayers.Util.getBrowserName() == "msie";
            while (par && !ismsie) {
                offsetTop += par.offsetTop;
                offsetLeft += par.offsetLeft;
                par = par.offsetParent;
            }
            var icon = document.getElementById('bigimg_close');
            icon.style.top = offsetTop;
            icon.style.left = offsetLeft + this.clientWidth - icon.clientWidth;

        };
        document.getElementById('bigimg').src = href;
    },

    closeBigImage: function() {
        document.getElementById('bigimg').src = "";
        document.getElementById('bigimg').parentNode.innerHTML = document.getElementById('bigimg').parentNode.innerHTML;
        document.getElementById('bigimg_container').style.display = "none";
    },

    Utils: {
        tlCorner: function(map, margin) {
            var bounds = map.calculateBounds();
            var corner = new OpenLayers.LonLat(bounds.left, bounds.top);
            var cornerAsPx = map.getPixelFromLonLat(corner);
            cornerAsPx = cornerAsPx.add( +margin, +margin);
            return map.getLonLatFromPixel(cornerAsPx);
        },

        trCorner: function(map, margin) {
            var bounds = map.calculateBounds();
            var corner = new OpenLayers.LonLat(bounds.right, bounds.top);
            var cornerAsPx = map.getPixelFromLonLat(corner);
            cornerAsPx = cornerAsPx.add( -margin, +margin);
            return map.getLonLatFromPixel(cornerAsPx);
        },

        brCorner: function(map, margin) {
            var bounds = map.calculateBounds();
            var corner = new OpenLayers.LonLat(bounds.right, bounds.bottom);
            var cornerAsPx = map.getPixelFromLonLat(corner);
            cornerAsPx = cornerAsPx.add( -margin, -margin);
            return map.getLonLatFromPixel(cornerAsPx);
        },

        blCorner: function(map, margin) {
            var bounds = map.calculateBounds();
            var corner = new OpenLayers.LonLat(bounds.left, bounds.bottom);
            var cornerAsPx = map.getPixelFromLonLat(corner);
            cornerAsPx = cornerAsPx.add( +margin, -margin);
            return map.getLonLatFromPixel(cornerAsPx);
        },

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

        displayUserMessage: function(message, status) {
            var div = document.getElementById('message');
            while (div.firstChild)
                div.removeChild(div.firstChild);
            var textNode = document.createTextNode(message);
            switch (status) {
                case "error":
                    div.style.color = "red";
                    break;
                case "warn":
                    div.style.color = "#FF8C00";
                    break;
                case "success":
                    div.style.color = "green";
                    break;
                default:
                    div.style.color = "black";
                    break;
            }
            div.style.display = "block";
            div.appendChild(textNode);
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
        }
    }
};

// if possible, determine language with HTTP_ACCEPT_LANGUAGE instead of
// navigator.language
if (OpenLayers.Lang[SypStrings.language]) {
    OpenLayers.Lang.setCode(SypStrings.language);
}

// avoid alerts
OpenLayers.Console.userError = function(error) { 
    SYP.Utils.displayUserMessage(error, "error");
}

// sometimes, especially when cache is clear, firefox does not compute
// correctly popup size. That's because at the end of getRenderedDimensions,
// dimensions of image is not known. Then, popup size is too small for its
// content. We work around the problem by checking that computed size is at
// least as big as content. To achieve that, we need to override
// OpenLayers.Popup.Anchored.prototype.updateSize to modify it slightly.
OpenLayers.Popup.Anchored.prototype.updateSize = function() {
    var self = this;

    window.setTimeout(function() { // timeout added by SYP

        // determine actual render dimensions of the contents by putting its
        // contents into a fake contentDiv (for the CSS) and then measuring it
        var preparedHTML = "<div class='" + self.contentDisplayClass+ "'>" + 
            self.contentDiv.innerHTML + 
            "</div>";

        var containerElement = (self.map) ? self.map.layerContainerDiv
                                          : document.body;
        var realSize = OpenLayers.Util.getRenderedDimensions(
            preparedHTML, null,	{
                displayClass: self.displayClass,
                containerElement: containerElement
            }
        );

        /*
         * XXX: next four lines are added by SYP!
         */
        if (self.contentDiv) {
            realSize.w = Math.max (realSize.w, self.contentDiv.scrollWidth);
            realSize.h = Math.max (realSize.h, self.contentDiv.scrollHeight);
        }

        // is the "real" size of the div is safe to display in our map?
        var safeSize = self.getSafeContentSize(realSize);

        var newSize = null;
        if (safeSize.equals(realSize)) {
            //real size of content is small enough to fit on the map, 
            // so we use real size.
            newSize = realSize;

        } else {

            //make a new OL.Size object with the clipped dimensions 
            // set or null if not clipped.
            var fixedSize = new OpenLayers.Size();
            fixedSize.w = (safeSize.w < realSize.w) ? safeSize.w : null;
            fixedSize.h = (safeSize.h < realSize.h) ? safeSize.h : null;

            if (fixedSize.w && fixedSize.h) {
                //content is too big in both directions, so we will use 
                // max popup size (safeSize), knowing well that it will 
                // overflow both ways.                
                newSize = safeSize;
            } else {
                //content is clipped in only one direction, so we need to 
                // run getRenderedDimensions() again with a fixed dimension
                var clippedSize = OpenLayers.Util.getRenderedDimensions(
                    preparedHTML, fixedSize, {
                        displayClass: self.contentDisplayClass,
                        containerElement: containerElement
                    }
                );

                //if the clipped size is still the same as the safeSize, 
                // that means that our content must be fixed in the 
                // offending direction. If overflow is 'auto', this means 
                // we are going to have a scrollbar for sure, so we must 
                // adjust for that.
                //
                var currentOverflow = OpenLayers.Element.getStyle(
                    self.contentDiv, "overflow"
                );
                if ( (currentOverflow != "hidden") && 
                     (clippedSize.equals(safeSize)) ) {
                    var scrollBar = OpenLayers.Util.getScrollbarWidth();
                    if (fixedSize.w) {
                        clippedSize.h += scrollBar;
                    } else {
                        clippedSize.w += scrollBar;
                    }
                }

                newSize = self.getSafeContentSize(clippedSize);
            }
        }                        
        self.setSize(newSize);     
    }, 0);
}
