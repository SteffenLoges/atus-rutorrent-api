plugin.init = function () {
  plugin.addPaneToStatusbar(
    "atus-td",
    $("<div style='padding:0 4px;opacity:.85;font-weight:bold;'>").text("ATUS")
  );
};

plugin.onRemove = function () {
  plugin.removePaneFromStatusbar("atus-td");
};

plugin.init();
