/**
 * JavaScript for form editing facetoface conditions.
 *
 * @module moodle-availability_facetoface-form
 */
M.availability_facetoface = M.availability_facetoface || {};

/**
 * @class M.availability_facetoface.form
 * @extends M.core_availability.plugin
 */
M.availability_facetoface.form = Y.Object(M.core_availability.plugin);

/**
 * facetofaces available for selection (alphabetical order).
 *
 * @property facetofaces
 * @type Array
 */
M.availability_facetoface.form.facetofaces = null;

/**
 * Initialises this plugin.
 *
 * @method initInner
 * @param {Array} facetofaces Array of objects containing facetofaceid => name
 */
M.availability_facetoface.form.initInner = function(facetofaces) {
    this.facetofaces = facetofaces;
};

M.availability_facetoface.form.getNode = function(json) {
    // Create HTML structure.
    var html = '<label><span class="pr-3">' + M.util.get_string('title', 'availability_facetoface') + '</span> ' +
        '<span class="availability-group">' +
        '<select name="id" class="custom-select">' +
        '<option value="choose">' + M.util.get_string('choosedots', 'moodle') + '</option>';
    for (var i = 0; i < this.facetofaces.length; i++) {
        var facetoface = this.facetofaces[i];
        // String has already been escaped using format_string.
        html += '<option value="' + facetoface.id + '">' + facetoface.name + '</option>';
    }
    html += '</select></span></label>';

    // Add "Effective from start date" checkbox.
    html += '<br><label><span class="pr-3">' + M.util.get_string('effectivefromstart', 'availability_facetoface') + '</span> ' +
        '<span class="availability-group">' +
        '<input type="checkbox" class="form-check-input mx-1" name="effectivefromstart"/>' +
        '</span></label>';

    var node = Y.Node.create('<span class="form-inline">' + html + '</span>');

    // Set initial value if specified.
    if (json.id !== undefined &&
        node.one('select[name=id] > option[value=' + json.id + ']')) {
        node.one('select[name=id]').set('value', '' + json.id);
    }
    if (json.effectivefromstart !== undefined && json.effectivefromstart === 1) {
        node.one('input[name=effectivefromstart]').set('checked', true);
    }

    // Add event handlers (first time only).
    if (!M.availability_facetoface.form.addedEvents) {
        M.availability_facetoface.form.addedEvents = true;
        var root = Y.one('.availability-field');
        root.delegate('change', function() {
            // Just update the form fields.
            M.core_availability.form.update();
        }, '.availability_facetoface select');

        root.delegate('click', function() {
            M.core_availability.form.update();
        }, '.availability_facetoface input[type=checkbox]');
    }

    return node;
};

M.availability_facetoface.form.fillValue = function(value, node) {
    var selected = node.one('select[name=id]').get('value');
    if (selected === 'choose') {
        value.id = 'choose';
    } else {
        value.id = parseInt(selected, 10);
    }
    if (node.one('input[name=effectivefromstart]').get('checked')) {
        value.effectivefromstart = 1;
    } else {
        value.effectivefromstart = 0;
    }
};

M.availability_facetoface.form.fillErrors = function(errors, node) {
    var value = {};
    this.fillValue(value, node);

    // Check facetoface item id.
    if (value.id === 'choose') {
        errors.push('availability_facetoface:error_selectfacetoface');
    }
};
