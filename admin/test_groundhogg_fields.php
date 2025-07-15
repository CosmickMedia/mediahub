<?php
require_once __DIR__.'/../lib/db.php';
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/groundhogg.php';
require_login();

$results = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $location = groundhogg_get_location();
    $email = 'fieldtest+' . time() . '@example.com';
    $contact = [
        'email'       => $email,
        'first_name'  => 'Field',
        'last_name'   => 'Tester',
        'mobile_phone'=> '555-123-4567',
        'address'     => $location['address'],
        'city'        => $location['city'],
        'state'       => $location['state'],
        'zip'         => $location['zip'],
        'country'     => $location['country'],
        'company_name'=> 'Test Company',
        'lead_source' => 'field-diagnostic',
        'tags'        => ['field-test']
    ];

    $built = groundhogg_build_contact_structure($contact);
    [$sendOk, $sendMsg] = groundhogg_send_contact($contact);
    $api = null;
    if ($sendOk) {
        [$getOk, $apiData] = groundhogg_get_contact($email);
        $api = $getOk ? $apiData : ['error' => $apiData];
    }

    $results = [
        'contact' => $contact,
        'built'   => $built,
        'sent'    => $sendOk,
        'message' => $sendMsg,
        'api'     => $api
    ];
}

include __DIR__.'/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Test Groundhogg Field Mapping</h4>
    <a href="settings.php" class="btn btn-sm btn-outline-secondary">Back to Settings</a>
</div>
<form method="post" class="mb-4">
    <button type="submit" class="btn btn-primary">Run Test</button>
</form>
<?php if ($results): ?>
    <h5>Raw Contact Data</h5>
    <pre><?php echo htmlspecialchars(json_encode($results['contact'], JSON_PRETTY_PRINT)); ?></pre>
    <h5>Built Groundhogg Data</h5>
    <pre><?php echo htmlspecialchars(json_encode($results['built'], JSON_PRETTY_PRINT)); ?></pre>
    <h5>Send Result</h5>
    <p><?php echo $results['sent'] ? 'Success' : 'Failed'; ?> - <?php echo htmlspecialchars($results['message']); ?></p>
    <?php if ($results['api'] !== null): ?>
        <h5>Contact From API</h5>
        <pre><?php echo htmlspecialchars(json_encode($results['api'], JSON_PRETTY_PRINT)); ?></pre>
    <?php endif; ?>
<?php endif; ?>
<?php include __DIR__.'/footer.php';
