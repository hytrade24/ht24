
/* ###VERSIONSBLOCKINLCUDE### */

less = {
  useFileCache: false  
};

function designGenerateCss(baseDirectory, successCallback, errorCallback) {
    // Prevent post on error
    var debug = less.render('@import "'+baseDirectory+'design.less";', {
        compress: true,
        returnRoot: true
    }).then(function (output) {
        successCallback(output);
    }, function(error) {
        if (typeof errorCallback != "undefined") {
            errorCallback(error);
        }
    });
}