$(document).foundation()

// Adds an entry to our debug area
function ui_add_log(message, color)
{
  var d = new Date();

  var dateString = (('0' + d.getHours())).slice(-2) + ':' +
    (('0' + d.getMinutes())).slice(-2) + ':' +
    (('0' + d.getSeconds())).slice(-2);

  color = (typeof color === 'undefined' ? 'muted' : color);

  var template = $('#debug-template').text();
  template = template.replace('%%date%%', dateString);
  template = template.replace('%%message%%', message);
  template = template.replace('%%color%%', color);
  
  $('#debug').find('li.empty').fadeOut(); // remove the 'no messages yet'
  $('#debug').prepend(template);
}

// Creates a new file and add it to our list
function ui_multi_add_file(id, file)
{
  var template = $('#files-template').text();
  template = template.replace('%%filename%%', file.name);

  template = $(template);
  template.prop('id', 'uploaderFile' + id);
  template.data('file-id', id);

  $('#files').find('li.empty').fadeOut(); // remove the 'no files yet'
  $('#files').append(template);
}

// Changes the status messages on our list
function ui_multi_update_file_status(id, status, message)
{
  $('#uploaderFile' + id).find('span').html(message).prop('class', 'status text-' + status);
}

// Updates a file progress, depending on the parameters it may animate it or change the color.
function ui_multi_update_file_progress(id, percent, color, active)
{
  color = (typeof color === 'undefined' ? false : color);
  active = (typeof active === 'undefined' ? true : active);

  var bar = $('#uploaderFile' + id).find('div.progress');
  var barmeter = $('#uploaderFile' + id).find('div.progress-meter');
  var bartext = $('#uploaderFile' + id).find('div.progress-meter-text');

  barmeter.width(percent + '%').attr('aria-valuenow', percent);
  barmeter.attr('aria-valuetext', percent + ' percent');
  //barmeter.toggleClass('progress-meter', active);

  if (percent === 0){
    bartext.html('');
  } else {
    bartext.html(percent + '%');
  }

  if (color !== false){
    bar.removeClass('primary success warning alert');
    bar.addClass(color);
  }
}

$(function(){
    $('.datepicker').datepicker({
        dateFormat: 'dd-mm-yy'
    });
    $('#editionSelector').click(function() {
        $(this).removeClass('secondary');
        $(this).addClass('primary');
        $('#magazineSelector').removeClass('primary');
        $('#magazineSelector').addClass('secondary');
        $('#magazine_details').hide();
        $('#pubDetailsForm')[0].reset();
        $('#pub_name').html('');
        $('#pub_type').val('edition');
    });
    $('#magazineSelector').click(function() {
        $(this).removeClass('secondary');
        $(this).addClass('primary');
        $('#editionSelector').removeClass('primary');
        $('#editionSelector').addClass('secondary');
        $('#magazine_details').show();
        $('#pubDetailsForm')[0].reset();
        $('#pub_name').html('');
        $('#pub_type').val('magazine');
    });
    $('.pub_data').keyup(function() {
        var pub_name = "";
        if($('#pub_type').val() === 'edition') {
            pub_name = $('#pubNumber').val() + '_' + $('#pubDate').val();
        }
        else {
            pub_name = 'M' + $('#pubNumber').val() + '_' + $('#pubDate').val() + '_' + $('#pubName').val().replace(/ /g, "-") + '-Magazine_' + $('#pubTheme').val().replace(/ /g, "-");
        }
        $('#pub_name').html(pub_name);
    });
    $('.pub_data').change(function() {
        var pub_name = "";
        if($('#pub_type').val() === 'edition') {
            pub_name = $('#pubNumber').val() + '_' + $('#pubDate').val();
        }
        else {
            pub_name = 'M' + $('#pubNumber').val() + '_' + $('#pubDate').val() + '_' + $('#pubName').val().replace(/ /g, "-") + '-Magazine_' + $('#pubTheme').val().replace(/ /g, "-");
        }
        $('#pub_name').html(pub_name);
    });
    $('#drag-and-drop-zone').dmUploader({ //
        url: 'ajaxResponse.php',
        maxFileSize: 128000000, // 128 Meg
        auto: true,
        queue: true,
        onDragEnter: function(){
            // Happens when dragging something over the DnD area
            this.addClass('active');
        },
        onDragLeave: function(){
            // Happens when dragging something OUT of the DnD area
            this.removeClass('active');
        },
        onInit: function(){
            // Plugin is ready to use
            ui_add_log('Uploader initialized', 'success');
        },
        onComplete: function(){
            // All files in the queue are processed (success or error)
            ui_add_log('All pending tranfers finished', 'primary');
        },
        onNewFile: function(id, file){
            // When a new file is added using the file selector or the DnD area
            ui_add_log('New file added #' + id, 'primary');
            ui_multi_add_file(id, file);
        },
        onBeforeUpload: function(id){
            // about tho start uploading a file
            ui_add_log('Starting the upload of #' + id, 'primary');
            ui_multi_update_file_status(id, 'uploading', 'Uploading...');
            ui_multi_update_file_progress(id, 0, 'primary', true);
        },
        onUploadCanceled: function(id) {
            // Happens when a file is directly canceled by the user.
            ui_multi_update_file_status(id, 'warning', 'Canceled by User');
            ui_multi_update_file_progress(id, 0, 'warning', false);
        },
        onUploadProgress: function(id, percent){
            // Updating file progress
            ui_multi_update_file_progress(id, percent);
        },
        onUploadSuccess: function(id, data){
            // A file was successfully uploaded
            ui_add_log('Server Response for file #' + id + ': ' + JSON.stringify(data), 'success');
            ui_add_log('Upload of file #' + id + ' COMPLETED', 'success');
            ui_multi_update_file_status(id, 'success', 'Upload Complete');
            ui_multi_update_file_progress(id, 100, 'success', false);
        },
        onUploadError: function(id, xhr, status, message){
            ui_add_log('Server Response for file #' + id + ': ' + status + ' - ' + message, 'alert');
            ui_multi_update_file_status(id, 'alert', message);
            ui_multi_update_file_progress(id, 0, 'alert', false);  
        },
        onFallbackMode: function(){
            // When the browser doesn't support this plugin :(
            ui_add_log('Plugin cant be used here, running Fallback callback', 'alert');
        },
        onFileSizeError: function(file){
            ui_add_log('File \'' + file.name + '\' cannot be added: size excess limit', 'alert');
        }
    });
    $('#btnApiStart').on('click', function(evt){
        evt.preventDefault();
        $('#drag-and-drop-zone').dmUploader('start');
    });
    $('#btnApiStop').on('click', function(evt){
        evt.preventDefault();
        $('#drag-and-drop-zone').dmUploader('cancel');
    });
    $('#btnApiCancel').on('click', function(evt){
        evt.preventDefault();
        $('#drag-and-drop-zone').dmUploader('reset');
    });
});