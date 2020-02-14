$(document).ready(function() {
  var cache = {};

  var fields = [
    {
      label: 'From',
      name: 'from',
      operator: ['exact', 'contains', 'not']
    },
    {
      label: 'To',
      name: 'to',
      operator: ['exact', 'contains', 'not']
    },
    {
      label: 'Subject',
      name: 'subject',
      operator: ['exact', 'contains', 'not']
    },
    {
      label: 'Status',
      name: 'status',
      operator: ['exact', 'contains', 'not']
    },
    {
      label: 'Remote IP',
      name: 'remoteip',
      operator: ['exact', 'not']
    },
    {
      label: 'Message ID',
      name: 'messageid',
      operator: ['exact', 'not']
    },
    {
      label: 'Action',
      name: 'action',
      operator: ['exact', 'not'],
      type: 'select',
      options: [
        'DELIVER',
        'QUEUE',
        'QUARANTINE',
        'ARCHIVE',
        'REJECT',
        'DELETE',
        'BOUNCE',
        'ERROR',
        'DEFER'
      ]
    },
    {
      label: 'Metadata',
      name: 'metadata',
      operator: ['exact', 'contains', 'not']
    }
  ];

  $('#filter-value').attr('disabled', true);

  fields.map(function (field, index) {
    $('#filter-field').append(
      $('<option>', {
        value: field.name,
        text: field.label
      })
    );
  });

  $('#filter-field').on('change', function(e) {
    $('#filter-operator').empty();
    var field = fields.find(i => i.name == e.currentTarget.value);
    if (typeof field == 'object') {
      field.operator.map(function (operator) {
        $('#filter-operator').append(
          $('<option>', {
            value: operator,
            text: operator
          })
        );
      });

      if (field.name == 'action') {
        $('#filter-value-field').html('<select class="custom-select" id="filter-value" name="filter-value"></select');
        field.options.map(function (option) {
          $('#filter-value').append('<option value="' + option + '">' + option + '</option>');
        });
      } else {
        $('#filter-value-field').html('<input type="text" class="form-control" id="filter-value" name="filter-value" size="30">');
      }

      $('#filter-value').attr('disabled', false);
    }
  });
});