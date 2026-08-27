<?php
require_once __DIR__.'/../includes/layout.php';
require_once __DIR__.'/../includes/auth.php';
$admin=require_admin();
page_header('New Report',true);
?>
<div class="section-title admin-page-title">
  <div>
    <h1>New Report / नयाँ विवरण</h1>
    <div class="muted">Choose the correct form. Enter only information that has been confirmed.</div>
  </div>
  <a class="btn btn-ghost" href="<?=e(base_url('admin/dashboard.php'))?>">Back to Cases</a>
</div>

<div class="operator-action-grid">
  <a class="operator-action" href="<?=e(base_url('admin/new-report/missing'))?>">
    <b>Missing Person Report</b>
    <small>हराइरहेको व्यक्तिको विवरण</small>
    <strong>Open Form</strong>
  </a>
  <a class="operator-action" href="<?=e(base_url('admin/new-report/rescue'))?>">
    <b>Rescue Request</b>
    <small>उद्धार अनुरोध</small>
    <strong>Open Form</strong>
  </a>
  <a class="operator-action" href="<?=e(base_url('rescued.php?staff=1'))?>">
    <b>Rescued Person</b>
    <small>उद्धार गरिएको व्यक्ति</small>
    <strong>Open Form</strong>
  </a>
  <a class="operator-action" href="<?=e(base_url('admin/deceased.php'))?>">
    <b>Recovered / Unidentified Deceased (DVI)</b>
    <small>फेला परेको / पहिचान नखुलेको शव</small>
    <strong>Restricted Form</strong>
  </a>
</div>

<div class="grid">
  <div class="col-7">
    <div class="card workflow-card">
      <h2>How to fill a report</h2>
      <div class="workflow-list">
        <div class="workflow-item"><span>1</span><div><b>Choose the correct report.</b><p>Use Missing Person when someone cannot be located; Rescue Request when someone is waiting; Rescued Person for a person already rescued by an official/rescuer/shelter; Dead Body Trace only for authorized recovered-deceased registration.</p></div></div>
        <div class="workflow-item"><span>2</span><div><b>Confirm the mobile number.</b><p>Read the number back once and make sure the phone can be reached.</p></div></div>
        <div class="workflow-item"><span>3</span><div><b>Enter only confirmed information.</b><p>Leave optional fields blank when information is not known. Do not guess.</p></div></div>
        <div class="workflow-item"><span>4</span><div><b>For a missing person, confirm the last contact.</b><p>Select the BS date, enter the time, and write the clearest known last-contact location.</p></div></div>
        <div class="workflow-item"><span>5</span><div><b>For a rescued person, choose the condition first: Safe, Injured, Semi-conscious or Unconscious. Then record only the fields shown and confirm the current shelter/hospital/institution.</b><p>GPS helps rescue teams, but a nearby road, village or landmark should also be written.</p></div></div>
        <div class="workflow-item"><span>6</span><div><b>Review the form before submitting.</b><p>Check the name, mobile number, location/date and situation once more.</p></div></div>
        <div class="workflow-item"><span>7</span><div><b>Save the Case ID.</b><p>After submission, keep the Case ID so the case can be tracked and updated later.</p></div></div>
      </div>
    </div>
  </div>
  <div class="col-5">
    <div class="card operator-check-card">
      <h2>Before submitting</h2>
      <div class="check-row">Name is spelled correctly</div>
      <div class="check-row">Mobile number is verified</div>
      <div class="check-row">Address or location is specific</div>
      <div class="check-row">Missing-person date and time are confirmed</div>
      <div class="check-row">GPS/location is captured where available</div>
      <div class="check-row">Rescued-person condition is correctly selected</div><div class="check-row">Dead body receives a Body Trace ID</div>
      <div class="check-row">Case ID is saved after submission</div>
    </div>
    <div class="card operator-tip-card">
      <h3>Important</h3>
      <p>Never invent information just to complete a form. If something is unknown, leave optional fields blank or explain it in Additional Notes.</p>
    </div>
  </div>
</div>
<?php page_footer(); ?>
