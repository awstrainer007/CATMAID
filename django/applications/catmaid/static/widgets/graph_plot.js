/* -*- mode: espresso; espresso-indent-level: 2; indent-tabs-mode: nil -*- */
/* vim: set softtabstop=2 shiftwidth=2 tabstop=2 expandtab: */

"use strict";

var CircuitGraphPlot = function() {
  this.widgetID = this.registerInstance();
  this.registerSource();

	// SkeletonModel instances
	this.skeletons = {};
  // Skeleton IDs, each has a model in this.skeletons
  // and the order corresponds with that of the adjacency matrix.
  this.skeleton_ids = [];

  this.AdjM = null;

	// From CircuitGraphAnalysis, first array entry is Signal Flow, rest are
	// the sorted pairs of [eigenvalue, eigenvector].
	this.vectors = null;

  // Array of arrays, containing anatomical measurements
  this.anatomy = null;

  // Array of arrays, containing centrality measures
  this.centralities = [null];

  this.names_visible = true;

  // Skeleton ID vs true
  this.selected = {};
};

CircuitGraphPlot.prototype = {};
$.extend(CircuitGraphPlot.prototype, new InstanceRegistry());
$.extend(CircuitGraphPlot.prototype, new SkeletonSource());

CircuitGraphPlot.prototype.getName = function() {
	return "Circuit Graph Plot " + this.widgetID;
};

CircuitGraphPlot.prototype.destroy = function() {
  this.unregisterInstance();
  this.unregisterSource();
  
  Object.keys(this).forEach(function(key) { delete this[key]; }, this);
};

CircuitGraphPlot.prototype.updateModels = function(models) {
	this.append(models);
};

/** Returns a clone of all skeleton models, keyed by skeleton ID. */
CircuitGraphPlot.prototype.getSelectedSkeletonModels = function() {
  if (!this.svg) return {};
	var skeletons = this.skeletons;
	return Object.keys(this.selected).reduce(function(o, skid) {
		o[skid] = skeletons[skid].clone();
		return o;
	}, {});
};

CircuitGraphPlot.prototype.getSkeletonModels = CircuitGraphPlot.prototype.getSelectedSkeletonModels;

CircuitGraphPlot.prototype.getSkeletonModel = function(skeleton_id) {
  var model = this.skeletons[skeleton_id];
  return model ? model.clone() : null;
};

CircuitGraphPlot.prototype.hasSkeleton = function(skeleton_id) {
  return skeleton_id in this.skeleton_ids;
};

CircuitGraphPlot.prototype.clear = function() {
	this.skeletons = {};
  this.skeleton_ids = [];
  this.selected = {};
  this.clearGUI();
};

CircuitGraphPlot.prototype.append = function(models) {
	// Update names and colors if present, remove when deselected
	Object.keys(this.skeletons).forEach(function(skid) {
		var model = models[skid];
		if (model) {
			if (model.selected) {
				this.skeletons[skid] = model;
			} else {
				delete this.skeletons[skid];
			}
		}
	}, this);

  Object.keys(models).forEach(function(skid) {
    if (skid in this.skeletons) return;
    var model = models[skid];
    if (model.selected) {
      this.skeletons[skid] = model.clone();
    }
  }, this);

	this.skeleton_ids = Object.keys(this.skeletons);

  if (1 === this.skeleton_ids.length) {
    this.clearGUI();
    growlAlert('Need more than one', 'Add at least another skeleton!');
    return;
  }

	// fetch connectivity data, create adjacency matrix and plot it
	requestQueue.register(django_url + project.id + '/skeletongroup/skeletonlist_confidence_compartment_subgraph', 'POST',
			{skeleton_list: this.skeleton_ids},
			(function(status, text) {
				if (200 !== status) return;
				var json = $.parseJSON(text);
				if (json.error) { alert(json.error); return; }
				// Create adjacency matrix
				var AdjM = this.skeleton_ids.map(function(skid) { return this.skeleton_ids.map(function(skid) { return 0; }); }, this);
				// Populate adjacency matrix
				var indices = this.skeleton_ids.reduce(function(o, skid, i) { o[skid] = i; return o; }, {});
				json.edges.forEach(function(edge) {
					AdjM[indices[edge[0]]][indices[edge[1]]] = edge[2];
				});
        // Update data and GUI
        this.plot(this.skeleton_ids, this.skeletons, AdjM);
			}).bind(this));
};

/** Takes an array of skeleton IDs, a map of skeleton ID vs SkeletonModel,
 * and an array of arrays representing the adjacency matrix where the order
 * in rows and columns corresponds to the order in the array of skeleton IDs.
 * Clears the existing plot and replaces it with the new data. */
CircuitGraphPlot.prototype.plot = function(skeleton_ids, models, AdjM) {
  // Set the new data
  this.skeleton_ids = skeleton_ids;
  this.skeletons = models;
  this.selected = {};
  this.AdjM = AdjM;

  // Compute signal flow and eigenvectors
  try {
    var cga = new CircuitGraphAnalysis().init(AdjM, 100000, 0.0000000001);
  } catch (e) {
    this.clear();
    console.log(e, e.stack);
    alert("Failed to compute the adjacency matrix: \n" + e + "\n" + e.stack);
    return;
  }

  // Reset data
  this.vectors = null;
  this.anatomy = null;
  this.centralities = [null];

  // Store for replotting later
  this.vectors = [[-1, cga.z]];
  for (var i=0; i<10 && i <cga.e.length; ++i) {
    this.vectors.push(cga.e[i]);
  }

  // Reset pulldown menus
  var updateSelect = function(select) {
    select.options.length = 0;

    select.options.add(new Option('Signal Flow', 0));
    for (var i=0; i<10 && i <cga.e.length; ++i) {
      select.options.add(new Option('Eigenvalue ' + Number(cga.e[i][0]).toFixed(2), i+1));
    }

    ['Cable length (nm)',
     'Num. input synapses',
     'Num. output synapses',
     'Num. input - Num. output'].forEach(function(name, k) {
       select.options.add(new Option(name, 'a' + k));
     });

    ['Betweenness centrality'].forEach(function(name, k) {
       select.options.add(new Option(name, 'c' + k));
     });

    return select;
  };

  updateSelect($('#circuit_graph_plot_X_' + this.widgetID)[0]).selectedIndex = 1;
  updateSelect($('#circuit_graph_plot_Y_' + this.widgetID)[0]).selectedIndex = 0;

  this.redraw();
};

CircuitGraphPlot.prototype.clearGUI = function() {
  this.selected = {};
  $('#circuit_graph_plot_div' + this.widgetID).empty();
};

CircuitGraphPlot.prototype.redraw = function() {
  if (!this.skeleton_ids || 0 === this.skeleton_ids.length) return;

  var xSelect = $('#circuit_graph_plot_X_' + this.widgetID)[0],
      ySelect = $('#circuit_graph_plot_Y_' + this.widgetID)[0];

  var f = (function(select) {
    var index = select.selectedIndex;
    if (index < this.vectors.length) {
      return this.vectors[index][1];
    } else if ('a' === select.value[0]) {
      if (!this.anatomy) {
        return this.loadAnatomy(this.redraw.bind(this));
      }
      return this.anatomy[parseInt(select.value.slice(1))];
    } else if ('c' === select.value[0]) {
      var i = parseInt(select.value.slice(1));
      if (!this.centralities[i]) {
        return this.loadBetweennessCentrality(this.redraw.bind(this));
      }
      return this.centralities[i];
    }
  }).bind(this);

  var xVector = f(xSelect),
      yVector = f(ySelect);

  if (!xVector || !yVector) return;

  this.draw(xVector, xSelect[xSelect.selectedIndex].text,
            yVector, ySelect[ySelect.selectedIndex].text);
};

CircuitGraphPlot.prototype.loadAnatomy = function(callback) {
  $.blockUI();
  requestQueue.register(django_url + project.id + '/skeletons/measure', "POST",
      {skeleton_ids: this.skeleton_ids},
      (function(status, text) {
        try {
          if (200 !== status) return;
          var json = $.parseJSON(text);
          if (json.error) { alert(json.error); return ;}
          // Map by skeleton ID
          var rows = json.reduce(function(o, row) {
            o[row[0]] = row;
            return o;
          }, {});
          // 0: cable length
          // 1: number of inputs
          // 2: number of outputs
          // 3: inputs minus outputs
          var vs = [[], [], [], []];
          this.skeleton_ids.forEach(function(skeleton_id) {
            var row = rows[skeleton_id];
            vs[0].push(row[2]);
            vs[1].push(row[3]);
            vs[2].push(row[4]);
            vs[3].push(row[3] - row[4]);
          });
          this.anatomy = vs;
          if (typeof(callback) === 'function') callback();
        } catch (e) {
          console.log(e, e.stack);
          alert("Error: " + e);
        } finally {
          $.unblockUI();
        }
      }).bind(this));
};

CircuitGraphPlot.prototype.loadBetweennessCentrality = function(callback) {
  try {
    var graph = jsnx.DiGraph();
    this.AdjM.forEach(function(row, i) {
      var source = this.skeleton_ids[i];
      row.forEach(function(count, j) {
        var target = this.skeleton_ids[j];
        graph.add_edge(source, target, {weight: count});
      }, this);
    }, this);

    if (this.skeleton_ids.length > 10) {
      $.blockUI();
    }

    var bc = jsnx.betweenness_centrality(graph, {weight: 'weight',
                                                 normalized: true});
    var max = Object.keys(bc).reduce(function(max, nodeID) {
      return Math.max(max, bc[nodeID]);
    }, 0);

    // Handle edge case
    if (0 === max) max = 1;

    this.centralities[0] = this.skeleton_ids.map(function(skeleton_id) {
      return bc[skeleton_id] / max;
    });

    if (typeof(callback) === 'function') callback();
  } catch (e) {
    console.log(e, e.stack);
    alert("Error: " + e);
  } finally {
    $.unblockUI();
  }
};

CircuitGraphPlot.prototype.draw = function(xVector, xTitle, yVector, yTitle) {
  var containerID = '#circuit_graph_plot_div' + this.widgetID,
      container = $(containerID);

  // Clear existing plot if any
  container.empty();

  // Recreate plot
  var margin = {top: 20, right: 20, bottom: 30, left: 40},
      width = container.width() - margin.left - margin.right,
      height = container.height() - margin.top - margin.bottom;

  // Package data
  var data = this.skeleton_ids.map(function(skid, i) {
    var model = this.skeletons[skid];
    return {skid: skid,
            name: model.baseName,
            hex: '#' + model.color.getHexString(),
            x: xVector[i],
            y: yVector[i]};
  }, this);

  // Define the ranges of the axes
  var xR = d3.scale.linear().domain(d3.extent(xVector)).nice().range([0, width]);
  var yR = d3.scale.linear().domain(d3.extent(yVector)).nice().range([height, 0]);

  // Define the data domains/axes
  var xAxis = d3.svg.axis().scale(xR)
                           .orient("bottom")
                           .ticks(10);
  var yAxis = d3.svg.axis().scale(yR)
                           .orient("left")
                           .ticks(10);

  var svg = d3.select(containerID).append("svg")
      .attr("id", "circuit_graph_plot" + this.widgetID)
      .attr("width", width + margin.left + margin.right)
      .attr("height", height + margin.top + margin.bottom)
    .append("g")
      .attr("transform", "translate(" + margin.left + ", " + margin.top + ")");

  // Add an invisible layer to enable triggering zoom from anywhere, and panning
  svg.append("rect")
    .attr("width", width)
    .attr("height", height)
    .style("opacity", "0");

  // Function that maps from data domain to plot coordinates
  var transform = function(d) { return "translate(" + xR(d.x) + "," + yR(d.y) + ")"; };

  // Create a 'g' group for each skeleton, containing a circle and the neuron name
  var elems = svg.selectAll(".state").data(data).enter()
    .append('g')
    .attr('transform', transform);

  var zoomed = function() {
    // Prevent panning beyond limits
    var translate = zoom.translate(),
        scale = zoom.scale(),
        tx = Math.min(0, Math.max(width * (1 - scale), translate[0])),
        ty = Math.min(0, Math.max(height * (1 - scale), translate[1]));

    zoom.translate([tx, ty]);

    // Scale as well the axes
    svg.select(".x.axis").call(xAxis);
    svg.select(".y.axis").call(yAxis);

    elems.attr('transform', transform);
  };

  // Variables exist throughout the scope of the function, so zoom is reachable from zoomed
  var zoom = d3.behavior.zoom().x(xR).y(yR).scaleExtent([1, 100]).on("zoom", zoomed);

  // Assign the zooming behavior to the encapsulating root group
  svg.call(zoom);

  var setSelected = (function(skid, b) {
    if (b) this.selected[skid] = true;
    else delete this.selected[skid];
  }).bind(this);

  var selected = this.selected;

  elems.append('circle')
     .attr('class', 'dot')
     .attr('r', function(d) { return selected[d.skid] ? 6 : 3; })
     .style('fill', function(d) { return d.hex; })
     .style('stroke', function(d) { return selected[d.skid] ? 'black' : 'grey'; })
     .on('click', function(d) {
       // Toggle selected:
       var c = d3.select(this);
       if (3 === Number(c.attr('r'))) {
         c.attr('r', 6).style('stroke', 'black');
         setSelected(d.skid, true);
       } else {
         c.attr('r', 3).style('stroke', 'grey');
         setSelected(d.skid, false);
       }
     })
   .append('svg:title')
     .text(function(d) { return d.name; });

  elems.append('text')
     .text(function(d) { return d.name; })
     .attr('id', 'name')
     .attr('display', this.names_visible ? '' : 'none')
     .attr('dx', function(d) { return 5; });

  // Insert the graphics for the axes (after the data, so that they draw on top)
  svg.append("g")
      .attr("class", "x axis")
      .attr("transform", "translate(0," + height + ")")
      .call(xAxis)
    .append("text")
      .attr("x", width)
      .attr("y", -6)
      .style("text-anchor", "end")
      .text(xTitle);

  svg.append("g")
      .attr("class", "y axis")
      .call(yAxis)
    .append("text")
      .attr("transform", "rotate(-90)")
      .attr("y", 6)
      .attr("dy", ".71em")
      .style("text-anchor", "end")
      .text(yTitle);

  this.svg = svg;
};

/** Redraw only the last request, where last is a period of about 1 second. */
CircuitGraphPlot.prototype.resize = function() {
  var now = new Date();
  // Overwrite request log if any
  this.last_request = now;

  setTimeout((function() {
    if (this.last_request && now === this.last_request) {
      delete this.last_request;
      this.redraw();
    }
  }).bind(this), 1000);
};

CircuitGraphPlot.prototype.setNamesVisible = function(v) {
  if (this.svg) {
    this.svg.selectAll('text#name').attr('display', v ? '' : 'none');
  }
};

CircuitGraphPlot.prototype.toggleNamesVisible = function(checkbox) {
  this.names_visible = checkbox.checked;
  this.setNamesVisible(this.names_visible);
};

/** Implements the refresh button. */
CircuitGraphPlot.prototype.update = function() {
  this.append(this.skeletons);
};

CircuitGraphPlot.prototype.highlight = function() {
  // TODO
};
