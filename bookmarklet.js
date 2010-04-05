var url = prompt("Enter a URL:");
if (url) {
  url = url.toLowerCase().replace(/\\/gm,'/').replace(/^\/?(.+)\/?$/gim,'$1');
  var fullPath = url.replace(/(?:([^\/]*)\/)?\.\./gim, '$1').replace(/[^a-z0-9\/]+/gim, '_').replace(/\/+/gim, '/');
  var text = "TinyAPI will attempt to find the following files and methods (in order):\n\n",
      methodPaths = [],
      parts = url.split("/"),
      u, m, p;
  for (var x=parts.length-1; x>0; x--) {
    u = parts.slice(0, x).join("/") + ".php";
    m = parts.slice(x).join("_").replace(/_$/gm,'');
    text += u + " -> " + m + "();\n"
  }
  
  window.alert(text);
}
