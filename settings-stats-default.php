<?php

$statsSettings['default-view'] = [['chart' => 'line', 'type' => 'action'], ['chart' => 'line', 'type' => 'bandwidth']];

$alpha = 0.65;

$statsSettings['color'] = [
  "rgba(130, 174, 245, $alpha)",
  "rgba(255, 99, 132, $alpha)",
  "rgba(255, 159, 64, $alpha)",
  "rgba(255, 205, 86, $alpha)",
  "rgba(75, 192, 192, $alpha)",
  "rgba(54, 162, 235, $alpha)",
  "rgba(153, 102, 255, $alpha)",
  "rgba(201, 203, 207, $alpha)",
  "rgba(136, 237, 101, $alpha)",
  "rgba(237, 88, 110, $alpha)",
  "rgba(237, 133, 218, $alpha)"
];

$statsSettings['label-color'] = [
  'REJECT' => [
    'bg' => "rgba(255, 99, 132, $alpha)"
  ],
  'QUARANTINE' => [
    'bg' => "rgba(255, 159, 64, $alpha)"
  ],
  'DEFER' => [
    'bg' => "rgba(197, 110, 181, $alpha)"
  ],
  'BOUNCE' => [
    'bg' => "rgba(48, 48, 48, $alpha)"
  ],
  'DELIVER' => [
    'bg' => "rgba(136, 237, 101, $alpha)"
  ],
  'bandwidth' => [
    'bg' => "rgba(134, 52, 113, $alpha)"
  ]
];

$statsSettings['aggregations'] = [
  'line' => [
    'action' => [
      'label' => 'Action',
      'groupby' => 'Inbound',
      'buckets' => [
        'aggregation' => [
          'key' => 'time',
          'type' => 'histogram',
          'field' => 'receivedtime',
          'aggregation' => [
            'key' => 'listener',
            'type' => 'filters',
            'filters' => [
              'inbound' => [
                'type' => 'phrase',
                'field' => 'serverid.keyword',
                'value' => 'mailserver:inbound'
              ]
            ],
            'aggregation' => [
              'key' => 'action',
              'type' => 'filters',
              'filters' => [
                'REJECT' => [
                  'type' => 'phrase',
                  'field' => 'action.keyword',
                  'value' => 'REJECT'
                ],
                'QUARANTINE' => [
                  'type' => 'phrase',
                  'field' => 'action.keyword',
                  'value' => 'QUARANTINE'
                ],
                'DEFER' => [
                  'type' => 'phrase',
                  'field' => 'action.keyword',
                  'value' => 'DEFER'
                ],
                'BOUNCE' => [
                  'type' => 'phrase',
                  'field' => 'queue.action.keyword',
                  'value' => 'BOUNCE'
                ],
                'DELIVER' => [
                  'type' => 'phrase',
                  'field' => 'queue.action.keyword',
                  'value' => 'DELIVER'
                ]
              ]
            ]
          ]
        ]
      ]
    ],
    'bandwidth' => [
      'label' => 'Bandwidth usage - MiB',
      'groupby' => 'Inbound',
      'splitseries' => false,
      'legend' => 'bandwidth',
      'buckets' => [
        'aggregation' => [
          'key' => 'time',
          'type' => 'histogram',
          'field' => 'receivedtime',
          'aggregation' => [
            'key' => 'listener',
            'type' => 'filters',
            'filters' => [
              'inbound' => [
                'type' => 'phrase',
                'field' => 'serverid.keyword',
                'value' => 'mailserver:inbound'
              ]
            ]
          ]
        ]
      ],
      'metrics' => [
        'key' => 'bandwidth',
        'type' => 'sum',
        'field' => 'size',
        'format' => function ($v) {
          return round($v / 1024 / 1024, 2);
        }
      ]
    ]
  ],
  'bar' => [
    'senderip' => [
      'label' => 'Remote IP\'s',
      'groupby' => 'Top (Inbound)',
      'buckets' => [
        'aggregation' => [
          'key' => 'listener',
          'type' => 'filters',
          'filters' => [
            'inbound' => [
              'type' => 'phrase',
              'field' => 'serverid.keyword',
              'value' => 'mailserver:inbound'
            ]
          ],
          'aggregation' => [
            'key' => 'ip',
            'type' => 'terms',
            'field' => 'senderip',
            'size' => 10,
            'sort' => 'desc',
          ]
        ]
      ]
    ],
    'senderdomain' => [
      'label' => 'Sender domains',
      'groupby' => 'Top (Inbound)',
      'legend' => 'top',
      'buckets' => [
        'aggregation' => [
          'key' => 'listener',
          'type' => 'filters',
          'filters' => [
            'inbound' => [
              'type' => 'phrase',
              'field' => 'serverid.keyword',
              'value' => 'mailserver:inbound'
            ]
          ],
          'aggregation' => [
            'key' => 'senders',
            'type' => 'terms',
            'field' => 'senderdomain.keyword',
            'size' => 10,
            'sort' => 'desc'
          ]
        ]
      ]
    ],
    'senders' => [
      'label' => 'Senders',
      'groupby' => 'Top (Inbound)',
      'buckets' => [
        'aggregation' => [
          'key' => 'listener',
          'type' => 'filters',
          'filters' => [
            'inbound' => [
              'type' => 'phrase',
              'field' => 'serverid.keyword',
              'value' => 'mailserver:inbound'
            ]
          ],
          'aggregation' => [
            'key' => 'senders',
            'type' => 'terms',
            'field' => 'sender.keyword',
            'size' => 10,
            'sort' => 'desc'
          ]
        ]
      ]
    ],
    'recipientdomain' => [
      'label' => 'Recipient domains',
      'groupby' => 'Top (Inbound)',
      'buckets' => [
        'aggregation' => [
          'key' => 'listener',
          'type' => 'filters',
          'filters' => [
            'inbound' => [
              'type' => 'phrase',
              'field' => 'serverid.keyword',
              'value' => 'mailserver:inbound'
            ]
          ],
          'aggregation' => [
            'key' => 'recipients',
            'type' => 'terms',
            'field' => 'recipientdomain.keyword',
            'size' => 10,
            'sort' => 'desc'
          ]
        ]
      ]
    ],
    'recipients' => [
      'label' => 'Recipients',
      'groupby' => 'Top (Inbound)',
      'buckets' => [
        'aggregation' => [
          'key' => 'listener',
          'type' => 'filters',
          'filters' => [
            'inbound' => [
              'type' => 'phrase',
              'field' => 'serverid.keyword',
              'value' => 'mailserver:inbound'
            ]
          ],
          'aggregation' => [
            'key' => 'recipients',
            'type' => 'terms',
            'field' => 'recipient.keyword',
            'size' => 10,
            'sort' => 'desc'
          ]
        ]
      ]
    ],
    'bandwidth' => [
      'label' => 'Bandwidth usage - MiB',
      'groupby' => 'Inbound',
      'buckets' => [
        'aggregation' => [
          'key' => 'listener',
          'type' => 'filters',
          'filters' => [
            'inbound' => [
              'type' => 'phrase',
              'field' => 'serverid.keyword',
              'value' => 'mailserver:inbound'
            ]
          ]
        ]
      ],
      'metrics' => [
        'key' => 'bandwidth',
        'type' => 'sum',
        'field' => 'size',
        'format' => function ($v) {
          return round($v / 1024 / 1024, 2);
        }
      ]
    ]
  ],
  'pie' => [
    'action' => [
      'label' => 'Action type',
      'groupby' => 'Inbound',
      'buckets' => [
        'aggregation' => [
          'key' => 'listener',
          'type' => 'filters',
          'filters' => [
            'inbound' => [
              'type' => 'phrase',
              'field' => 'serverid.keyword',
              'value' => 'mailserver:inbound'
            ]
          ],
          'aggregation' => [
            'key' => 'action',
            'type' => 'filters',
            'filters' => [
              'REJECT' => [
                'type' => 'phrase',
                'field' => 'action.keyword',
                'value' => 'REJECT'
              ],
              'QUARANTINE' => [
                'type' => 'phrase',
                'field' => 'action.keyword',
                'value' => 'QUARANTINE'
              ],
              'DEFER' => [
                'type' => 'phrase',
                'field' => 'action.keyword',
                'value' => 'DEFER'
              ],
              'BOUNCE' => [
                'type' => 'phrase',
                'field' => 'queue.action.keyword',
                'value' => 'BOUNCE'
              ],
              'DELIVER' => [
                'type' => 'phrase',
                'field' => 'queue.action.keyword',
                'value' => 'DELIVER'
              ]
            ]
          ]
        ]
      ]
    ],
    'classification' => [
      'label' => 'Spam classification',
      'groupby' => 'Inbound',
      'buckets' => [
        'aggregation' => [
          'key' => 'listener',
          'type' => 'filters',
          'filters' => [
            'inbound' => [
              'type' => 'phrase',
              'field' => 'serverid.keyword',
              'value' => 'mailserver:inbound'
            ]
          ],
          'aggregation' => [
            'key' => 'spam',
            'type' => 'filters',
            'filters' => [
              'non-spam' => [
                'type' => 'phrase',
                'field' => 'score_rpd',
                'value' => '0'
              ],
              'suspect' => [
                'type' => 'phrase',
                'field' => 'score_rpd',
                'value' => '10'
              ],
              'valid-bulk' => [
                'type' => 'phrase',
                'field' => 'score_rpd',
                'value' => '40'
              ],
              'bulk' => [
                'type' => 'phrase',
                'field' => 'score_rpd',
                'value' => '50'
              ],
              'spam' => [
                'type' => 'phrase',
                'field' => 'score_rpd',
                'value' => '100'
              ]
            ]
          ]
        ]
      ]
    ]
  ]
];

