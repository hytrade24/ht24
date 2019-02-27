
/* ###VERSIONSBLOCKINLCUDE### */

function Rating(min, max, def) {
	// Basic settings
    this.min = min;
    this.max = max;
    this.value = -1;
    
    // Events
    this.target_input = false;
    
    // HTML Part
    this.html = document.createElement("div");
    this.buttons = new Array();
    for (var i = min; i <= max; i++) {
      var button = new Object();
      button.link = document.createElement("a");
      button.link.value = i;
      button.link.parent = this;
      button.img = document.createElement("img");
      if (i <= this.value) {
    	button.img.src = ebiz_trader_baseurl+"gfx/star.png";
      } else {
    	button.img.src = ebiz_trader_baseurl+"gfx/star_blank.png";
      }
      button.link.onclick = function() { this.parent.ChangeValue(this.value); };
      button.link.onmouseover = function() { this.parent.PreviewValue(this.value); };
      button.link.onmouseout = function() { this.parent.PreviewAbort(this.value); };
      button.link.appendChild(button.img);
      this.html.appendChild(button.link);
      this.buttons.push(button);
    }
    
    // Update default value
    this.ChangeValue(this.value);
}

// Functions
Rating.prototype.SetInput = function(input_field) {
	input_field = jQuery(input_field);
	this.target_input = input_field;
	if ((input_field.val() >= this.min) && (input_field.val() <= this.max))
	  this.ChangeValue(input_field.val());
};

Rating.prototype.ChangeValue = function(value) {
	// if ((value > this.max) || (value < this.min)) {
	//   // Invalid value
	//   return;
	// }
	
	this.value = value;
	for (var i = this.min; i <= this.max; i++) {
	  var button = this.buttons[i-this.min];
	  if (i <= this.value) {
	  	button.img.src = ebiz_trader_baseurl+"gfx/star.png";
	  } else {
	   	button.img.src = ebiz_trader_baseurl+"gfx/star_blank.png";
	  }
	}
	if (this.target_input) {
	  this.target_input.val(this.value);
	}
};

Rating.prototype.PreviewValue = function(value) {
	if ((value > this.max) || (value < this.min)) {
	  // Invalid value
	  return;
	}
	
	for (var i = this.min; i <= this.max; i++) {
	  var button = this.buttons[i-this.min];
	  if (i <= value) {
	  	button.img.src = ebiz_trader_baseurl+"gfx/star_half.png";
	  } else {
	   	button.img.src = ebiz_trader_baseurl+"gfx/star_blank.png";
	  }
	}
};

Rating.prototype.PreviewAbort = function() {
	this.ChangeValue(this.value);
};

/*
var Rating = Class.create();

Rating.prototype = {
  initialize: function(min, max, def) {
	// Basic settings
    this.min = min;
    this.max = max;
    this.value = -1;
    
    // Events
    this.target_input = false;
    
    // HTML Part
    this.html = document.createElement("div");
    this.buttons = new Array();
    for (var i = min; i <= max; i++) {
      var button = new Object();
      button.link = document.createElement("img");
      button.link.value = i;
      button.link.parent = this;
      button.img = document.createElement("img");
      if (i <= this.value) {
    	button.img.src = "/gfx/star.png";
      } else {
    	button.img.src = "/gfx/star_blank.png";
      }
      button.link.onclick = function() { this.parent.ChangeValue(this.value); };
      button.link.onmouseover = function() { this.parent.PreviewValue(this.value); };
      button.link.onmouseout = function() { this.parent.PreviewAbort(this.value); };
      button.link.appendChild(button.img);
      this.html.appendChild(button.link);
      this.buttons.push(button);
    }
    
    // Update default value
    this.ChangeValue(this.value);
  },
  SetInput: function(input_field) {
	if (typeof(input_field) == 'string')
	  input_field = jQuery(input_field);
	this.target_input = input_field;
	if ((input_field.value >= this.min) && (input_field.value <= this.max))
	  this.ChangeValue(input_field.value);
  },
  ChangeValue: function(value) {
		if ((value > this.max) || (value < this.min)) {
		  // Invalid value
		  return;
		}
		
		this.value = value;
		for (var i = this.min; i <= this.max; i++) {
		  var button = this.buttons[i-this.min];
		  if (i <= this.value) {
		  	button.img.src = "/gfx/star.png";
		  } else {
		   	button.img.src = "/gfx/star_blank.png";
		  }
		}
		if (this.target_input) {
		  this.target_input.value = this.value;
		}
	  },
  PreviewValue: function(value) {
	if ((value > this.max) || (value < this.min)) {
	  // Invalid value
	  return;
	}
	
	for (var i = this.min; i <= this.max; i++) {
	  var button = this.buttons[i-this.min];
	  if (i <= value) {
	  	button.img.src = "/gfx/star_half.png";
	  } else {
	   	button.img.src = "/gfx/star_blank.png";
	  }
	}
  },
  PreviewAbort: function() {
	this.ChangeValue(this.value);
  }
};
*/