/*
YUI 3.14.1 (build 63049cb)
Copyright 2013 Yahoo! Inc. All rights reserved.
Licensed under the BSD License.
http://yuilibrary.com/license/
*/

YUI.add("node-pluginhost",function(e,t){e.Node.plug=function(){var t=e.Array(arguments);return t.unshift(e.Node),e.Plugin.Host.plug.apply(e.Base,t),e.Node},e.Node.unplug=function(){var t=e.Array(arguments);return t.unshift(e.Node),e.Plugin.Host.unplug.apply(e.Base,t),e.Node},e.mix(e.Node,e.Plugin.Host,!1,null,1),e.Object.each(e.Node._instances,function(t){e.Plugin.Host.apply(t)}),e.NodeList.prototype.plug=function(){var t=arguments;return e.NodeList.each(this,function(n){e.Node.prototype.plug.apply(e.one(n),t)}),this},e.NodeList.prototype.unplug=function(){var t=arguments;return e.NodeList.each(this,function(n){e.Node.prototype.unplug.apply(e.one(n),t)}),this}},"3.14.1",{requires:["node-base","pluginhost"]});
