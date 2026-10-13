<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$staff = require_staff();
$is_admin = $staff['role'] === 'administrator';

$action = get_str('action', 10) ?: 'list';
$id = get_int('id');
$errors = [];
$form = null;

// ----- POST handlers -----
if (is_post()) {
    $op = post_str('op', 30);
    $record_id = post_int('record_id');
    $record = $record_id ? find_record($record_id) : null;

    if ($op === 'save') {
        if ($record_id && !$record) {
            not_found();
        }
        [$data, $errors] = validate_record_input($staff, $record);
        if (!$errors) {
            $was_published = $record && $record['status'] === 'published';
            $saved_id = save_record($data, $staff, $record);
            audit($record ? 'record_updated' : 'record_created', 'record', $saved_id, $data['title'] . ' [' . $data['status'] . '/' . $data['visibility'] . ']');
            if ($data['status'] === 'published' && !$was_published) {
                audit('record_published', 'record', $saved_id);
            }
            [$uploaded, $upload_errors] = store_uploads($saved_id, $_FILES['media'] ?? [], isset($_POST['media_confidential']), post_str('media_caption', 255), (int) $staff['id']);
            foreach ($upload_errors as $upload_error) {
                flash('warning', $upload_error);
            }
            if ($uploaded) {
                audit('media_uploaded', 'record', $saved_id, "$uploaded file(s)");
            }
            if (!$is_admin && $was_published && $data['status'] !== 'published') {
                flash('warning', 'Your changes were saved and sent back to an administrator for review, so the record is hidden from the public until it is approved again.');
            }
            flash('success', $record ? 'Record updated.' : 'Record created.' . ($uploaded ? " $uploaded file(s) uploaded." : ''));
            redirect('admin/records.php', ['action' => 'view', 'id' => $saved_id]);
        }
        $form = $data;
        $action = $record ? 'edit' : 'add';
        $id = $record_id;
    } elseif (!$record) {
        not_found();
    } elseif ($op === 'delete') {
        if (!$is_admin) {
            render_error_page(403, 'Access Denied', 'Only administrators can delete records.');
        }
        db_run('DELETE FROM heritage_records WHERE id = ?', [$record_id]);
        audit('record_deleted', 'record', $record_id, $record['title']);
        flash('success', 'Record “' . $record['title'] . '” and its files were deleted.');
        redirect('admin/records.php');
    } elseif ($op === 'set_status') {
        $new_status = post_str('status', 20);
        $allowed = $is_admin ? array_keys(RECORD_STATUSES) : ['draft', 'pending'];
        if (!in_array($new_status, $allowed, true)) {
            render_error_page(403, 'Access Denied', 'You are not allowed to set that status.');
        }
        if ($new_status === 'published' && ($problems = publication_problems($record))) {
            foreach ($problems as $problem) {
                flash('error', $problem);
            }
        } else {
            $extra = $new_status === 'published' ? ', approved_by = ?, approved_at = NOW(), published_at = COALESCE(published_at, NOW())' : '';
            $params = [$new_status, $staff['id']];
            if ($extra) {
                $params[] = $staff['id'];
            }
            $params[] = $record_id;
            db_run("UPDATE heritage_records SET status = ?, updated_by = ?$extra WHERE id = ?", $params);
            audit('record_status_' . $new_status, 'record', $record_id);
            flash('success', 'Status changed to ' . RECORD_STATUSES[$new_status] . '.');
        }
        redirect('admin/records.php', ['action' => 'view', 'id' => $record_id]);
    } elseif ($op === 'upload') {
        [$uploaded, $upload_errors] = store_uploads($record_id, $_FILES['media'] ?? [], isset($_POST['media_confidential']), post_str('media_caption', 255), (int) $staff['id']);
        foreach ($upload_errors as $upload_error) {
            flash('error', $upload_error);
        }
        if ($uploaded) {
            audit('media_uploaded', 'record', $record_id, "$uploaded file(s)");
            flash('success', "$uploaded file(s) uploaded.");
        } elseif (!$upload_errors) {
            flash('warning', 'Please choose at least one file to upload.');
        }
        redirect('admin/records.php', ['action' => 'view', 'id' => $record_id]);
    } elseif (in_array($op, ['media_delete', 'media_primary', 'media_confidential'], true)) {
        $media_id = post_int('media_id');
        $media = db_one('SELECT id, kind, is_confidential, original_name FROM record_media WHERE id = ? AND record_id = ?', [$media_id, $record_id]);
        if (!$media) {
            not_found();
        }
        if ($op === 'media_delete') {
            if (!$is_admin) {
                render_error_page(403, 'Access Denied', 'Only administrators can delete files.');
            }
            db_run('DELETE FROM record_media WHERE id = ?', [$media_id]);
            audit('media_deleted', 'record', $record_id, $media['original_name']);
            flash('success', 'File deleted.');
        } elseif ($op === 'media_primary' && $media['kind'] === 'image') {
            db_run('UPDATE record_media SET is_primary = (id = ?) WHERE record_id = ? AND kind = \'image\'', [$media_id, $record_id]);
            db_run('UPDATE record_media SET is_confidential = 0 WHERE id = ?', [$media_id]);
            flash('success', 'Cover image updated.');
        } elseif ($op === 'media_confidential') {
            $value = (int) $media['is_confidential'] ? 0 : 1;
            db_run('UPDATE record_media SET is_confidential = ?, is_primary = IF(? = 1, 0, is_primary) WHERE id = ?', [$value, $value, $media_id]);
            audit('media_' . ($value ? 'restricted' : 'unrestricted'), 'record', $record_id, $media['original_name']);
            flash('success', $value ? 'File marked confidential (staff only).' : 'File now follows the record\'s visibility.');
        }
        redirect('admin/records.php', ['action' => 'view', 'id' => $record_id]);
    } else {
        render_error_page(400, 'Unknown Action', 'The requested action is not supported.');
    }
}

$categories = fetch_categories();

// ----- Add / edit form -----
if ($action === 'add' || $action === 'edit') {
    $record = $action === 'edit' ? find_record($id) : null;
    if ($action === 'edit' && !$record) {
        not_found();
    }
    if ($form === null) {
        $form = $record ? array_intersect_key($record, record_form_defaults()) + record_form_defaults() : record_form_defaults();
        if (!$record && ($preset = get_int('category_id'))) {
            $form['category_id'] = $preset;
        }
    }
    $status_options = $is_admin ? RECORD_STATUSES : array_intersect_key(RECORD_STATUSES, ['draft' => 1, 'pending' => 1]);

    $admin_title = $record ? 'Edit Record' : 'Add Heritage Record';
    $admin_active = 'records';
    require dirname(__DIR__) . '/includes/partials/admin_header.php';
    ?>
    <div class="admin-header">
        <div>
            <h1><?php echo $record ? '✏️ Edit: ' . e($record['title']) : '➕ Add Heritage Record'; ?></h1>
            <p class="admin-subtitle">Fields marked * are required. Records stay hidden from the public until they are published.</p>
        </div>
        <a href="<?php echo e($record ? url('admin/records.php', ['action' => 'view', 'id' => $record['id']]) : url('admin/records.php')); ?>" class="btn btn-secondary">&larr; Back</a>
    </div>

    <?php foreach ($errors as $error): ?>
        <div class="form-alert form-alert-danger" role="alert"><span>⚠</span><span><?php echo e($error); ?></span></div>
    <?php endforeach; ?>
    <?php if (!$is_admin && $record && $record['status'] === 'published'): ?>
        <div class="form-alert form-alert-warning"><span>ℹ</span><span>This record is published. Saving changes sends it back for administrator review.</span></div>
    <?php endif; ?>

    <form method="POST" action="<?php echo e(url('admin/records.php')); ?>" enctype="multipart/form-data" class="record-form">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="op" value="save">
        <input type="hidden" name="record_id" value="<?php echo (int) ($record['id'] ?? 0); ?>">

        <section class="admin-card form-section">
            <div class="admin-card-header"><h2>1. Record Information</h2></div>
            <div class="form-grid">
                <div class="form-group form-span-2">
                    <label class="form-label" for="title">Title *</label>
                    <input class="form-input" id="title" name="title" maxlength="200" required value="<?php echo e($form['title']); ?>" placeholder="e.g. Hudhud chants of the Ifugao">
                </div>
                <div class="form-group">
                    <label class="form-label" for="category_id">Category *</label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <option value="">Choose a category…</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo (int) $category['id']; ?>"<?php echo selected($category['id'], $form['category_id']); ?>><?php echo e($category['icon'] . ' ' . $category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="date_recorded">Date Recorded</label>
                    <input class="form-input" type="date" id="date_recorded" name="date_recorded" value="<?php echo e($form['date_recorded']); ?>">
                </div>
                <div class="form-group form-span-2">
                    <label class="form-label" for="summary">Short Summary</label>
                    <input class="form-input" id="summary" name="summary" maxlength="500" value="<?php echo e($form['summary']); ?>" placeholder="One or two sentences shown in search results">
                </div>
                <div class="form-group form-span-2">
                    <label class="form-label" for="description">Full Description *</label>
                    <textarea class="form-textarea form-textarea-lg" id="description" name="description" required maxlength="60000"><?php echo e($form['description']); ?></textarea>
                </div>
                <div class="form-group form-span-2">
                    <label class="form-label" for="cultural_significance">Cultural Significance</label>
                    <textarea class="form-textarea" id="cultural_significance" name="cultural_significance" maxlength="20000"><?php echo e($form['cultural_significance']); ?></textarea>
                </div>
            </div>
        </section>

        <section class="admin-card form-section">
            <div class="admin-card-header"><h2>2. Cultural Context</h2></div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="community">Community / Cultural Group</label>
                    <input class="form-input" id="community" name="community" maxlength="150" value="<?php echo e($form['community']); ?>" placeholder="e.g. Ifugao">
                </div>
                <div class="form-group">
                    <label class="form-label" for="language">Language</label>
                    <input class="form-input" id="language" name="language" maxlength="100" value="<?php echo e($form['language']); ?>" placeholder="e.g. Tuwali Ifugao">
                </div>
                <div class="form-group">
                    <label class="form-label" for="source">Knowledge Holder / Source</label>
                    <input class="form-input" id="source" name="source" maxlength="200" value="<?php echo e($form['source']); ?>" placeholder="e.g. Elder name, archive reference">
                </div>
                <div class="form-group">
                    <label class="form-label" for="period">Period / Era</label>
                    <input class="form-input" id="period" name="period" maxlength="100" value="<?php echo e($form['period']); ?>" placeholder="e.g. Pre-colonial, 1950s">
                </div>
                <div class="form-group form-span-2">
                    <label class="form-label" for="keywords">Keywords / Materials</label>
                    <input class="form-input" id="keywords" name="keywords" maxlength="500" value="<?php echo e($form['keywords']); ?>" placeholder="Comma-separated, e.g. weaving, abaca, harvest">
                </div>
                <div class="form-group form-span-2">
                    <label class="form-label" for="location">Location</label>
                    <input class="form-input" id="location" name="location" maxlength="200" value="<?php echo e($form['location']); ?>" placeholder="Village, municipality, or site">
                    <label class="form-check"><input type="checkbox" name="location_is_sensitive" value="1"<?php echo checked((int) $form['location_is_sensitive']); ?>> <span>Culturally sensitive location: hide it from everyone except staff</span></label>
                </div>
            </div>
        </section>

        <section class="admin-card form-section">
            <div class="admin-card-header"><h2>3. Privacy &amp; Community Consent</h2></div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="visibility">Visibility *</label>
                    <select class="form-select" id="visibility" name="visibility" required>
                        <?php foreach (VISIBILITY_LEVELS as $value => $label): ?>
                            <option value="<?php echo e($value); ?>"<?php echo selected($value, $form['visibility']); ?>><?php echo e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <span class="form-label">Sensitivity</span>
                    <label class="form-check form-check-boxed"><input type="checkbox" name="is_sacred" value="1"<?php echo checked((int) $form['is_sacred']); ?>> <span>Sacred or confidential knowledge (can never be Public)</span></label>
                </div>
                <div class="form-group">
                    <label class="form-label" for="consent_status">Consent Status *</label>
                    <select class="form-select" id="consent_status" name="consent_status" required>
                        <?php foreach (CONSENT_STATUSES as $value => $label): ?>
                            <option value="<?php echo e($value); ?>"<?php echo selected($value, $form['consent_status']); ?>><?php echo e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="consent_date">Consent Date</label>
                    <input class="form-input" type="date" id="consent_date" name="consent_date" value="<?php echo e($form['consent_date']); ?>">
                </div>
                <div class="form-group form-span-2">
                    <label class="form-label" for="consent_given_by">Consent Given By</label>
                    <input class="form-input" id="consent_given_by" name="consent_given_by" maxlength="200" value="<?php echo e($form['consent_given_by']); ?>" placeholder="Community council, elder, or custodian who authorized sharing">
                </div>
                <div class="form-group form-span-2">
                    <label class="form-label" for="consent_notes">Consent &amp; Usage Notes (staff only)</label>
                    <textarea class="form-textarea" id="consent_notes" name="consent_notes" maxlength="5000" placeholder="Conditions, restrictions, or reference to the consent document"><?php echo e($form['consent_notes']); ?></textarea>
                </div>
            </div>
        </section>

        <section class="admin-card form-section">
            <div class="admin-card-header"><h2>4. Publication &amp; Files</h2></div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="status">Publication Status *</label>
                    <select class="form-select" id="status" name="status" required>
                        <?php foreach ($status_options as $value => $label): ?>
                            <option value="<?php echo e($value); ?>"<?php echo selected($value, $form['status']); ?>><?php echo e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-help"><?php echo $is_admin ? 'Publishing requires recorded consent. Sacred records cannot be Public.' : 'Editors submit records for review; an administrator publishes them.'; ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="media_caption">Caption for New Files</label>
                    <input class="form-input" id="media_caption" name="media_caption" maxlength="255" placeholder="Optional">
                </div>
                <div class="form-group form-span-2">
                    <span class="form-label">Attach Images or Documents</span>
                    <label class="file-upload-zone" for="media">
                        <span class="file-upload-icon">📎</span>
                        <span class="file-upload-title">Choose up to <?php echo MAX_FILES_PER_UPLOAD; ?> files</span>
                        <span class="form-help">JPG, PNG, GIF, WEBP, PDF, DOCX, or TXT, up to <?php echo e(format_bytes((int) app_config('upload_max_bytes'))); ?> each</span>
                        <input type="file" id="media" name="media[]" class="form-file-input" multiple accept="image/jpeg,image/png,image/gif,image/webp,application/pdf,text/plain,.docx">
                    </label>
                    <div class="file-preview-list" data-file-preview-for="media"></div>
                    <label class="form-check"><input type="checkbox" name="media_confidential" value="1"> <span>Mark these files confidential (staff only, even if the record is public)</span></label>
                </div>
            </div>
        </section>

        <div class="form-actions form-actions-sticky">
            <button type="submit" class="btn btn-primary btn-lg"><?php echo $record ? 'Save Changes 💾' : 'Create Record 📚'; ?></button>
            <a href="<?php echo e($record ? url('admin/records.php', ['action' => 'view', 'id' => $record['id']]) : url('admin/records.php')); ?>" class="btn btn-secondary btn-lg">Cancel</a>
        </div>
    </form>
    <?php
    require dirname(__DIR__) . '/includes/partials/admin_footer.php';
    exit;
}

// ----- Record detail -----
if ($action === 'view') {
    $record = find_record($id);
    if (!$record) {
        not_found();
    }
    $media = record_media_list($id);
    $problems = publication_problems($record);
    $history = db_all("SELECT a.action, a.details, a.created_at, ad.username FROM audit_log a LEFT JOIN admins ad ON ad.id = a.actor_id AND a.actor_type = 'staff' WHERE a.entity = 'record' AND a.entity_id = ? ORDER BY a.id DESC LIMIT 10", [$id]);

    $admin_title = 'View Record';
    $admin_active = 'records';
    require dirname(__DIR__) . '/includes/partials/admin_header.php';
    ?>
    <div class="admin-header">
        <div>
            <h1><?php echo e($record['title']); ?></h1>
            <div class="badge-row">
                <?php echo status_badge($record['status']); ?>
                <?php echo visibility_badge($record['visibility']); ?>
                <?php echo consent_badge($record['consent_status']); ?>
                <?php if ((int) $record['is_sacred']): ?><span class="badge badge-danger">Sacred</span><?php endif; ?>
            </div>
        </div>
        <div class="button-row">
            <a href="<?php echo e(url('admin/records.php')); ?>" class="btn btn-secondary">&larr; All Records</a>
            <a href="<?php echo e(url('admin/records.php', ['action' => 'edit', 'id' => $id])); ?>" class="btn btn-primary">Edit ✏️</a>
            <a href="<?php echo e(url('record.php', ['id' => $id])); ?>" class="btn btn-secondary" target="_blank" rel="noopener">Preview ↗</a>
        </div>
    </div>

    <div class="admin-grid-2 admin-grid-wide-left">
        <div>
            <section class="admin-card">
                <div class="admin-card-header"><h2>Record Content</h2></div>
                <div class="admin-card-body">
                    <?php if ($record['summary']): ?><p class="record-summary"><?php echo e($record['summary']); ?></p><?php endif; ?>
                    <h3 class="admin-subheading">Description</h3>
                    <div class="record-text"><?php echo nl2br(e($record['description'])); ?></div>
                    <?php if ($record['cultural_significance']): ?>
                        <h3 class="admin-subheading">Cultural Significance</h3>
                        <div class="record-text"><?php echo nl2br(e($record['cultural_significance'])); ?></div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="admin-card">
                <div class="admin-card-header"><h2>Images &amp; Documents (<?php echo count($media); ?>)</h2></div>
                <div class="admin-card-body">
                    <?php if ($media): ?>
                        <ul class="media-admin-list">
                            <?php foreach ($media as $item): ?>
                                <li class="media-admin-item">
                                    <a href="<?php echo e(media_url((int) $item['id'])); ?>" target="_blank" rel="noopener" class="media-admin-thumb">
                                        <?php if ($item['kind'] === 'image'): ?>
                                            <img src="<?php echo e(media_url((int) $item['id'], true)); ?>" alt="" loading="lazy">
                                        <?php else: ?>
                                            <span>📄</span>
                                        <?php endif; ?>
                                    </a>
                                    <div class="media-admin-info">
                                        <strong><?php echo e($item['caption'] ?: $item['original_name']); ?></strong>
                                        <span><?php echo e($item['original_name']); ?> &bull; <?php echo e(format_bytes((int) $item['file_size'])); ?></span>
                                        <span>
                                            <?php if ((int) $item['is_primary']): ?><span class="badge badge-success">Cover</span><?php endif; ?>
                                            <?php if ((int) $item['is_confidential']): ?><span class="badge badge-danger">Confidential</span><?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="table-actions">
                                        <?php if ($item['kind'] === 'image' && !(int) $item['is_primary']): ?>
                                            <form method="POST" action="<?php echo e(url('admin/records.php')); ?>">
                                                <?php echo csrf_field(); ?><input type="hidden" name="op" value="media_primary"><input type="hidden" name="record_id" value="<?php echo $id; ?>"><input type="hidden" name="media_id" value="<?php echo (int) $item['id']; ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm">Set cover</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" action="<?php echo e(url('admin/records.php')); ?>">
                                            <?php echo csrf_field(); ?><input type="hidden" name="op" value="media_confidential"><input type="hidden" name="record_id" value="<?php echo $id; ?>"><input type="hidden" name="media_id" value="<?php echo (int) $item['id']; ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm"><?php echo (int) $item['is_confidential'] ? 'Unrestrict' : 'Make confidential'; ?></button>
                                        </form>
                                        <?php if ($is_admin): ?>
                                            <form method="POST" action="<?php echo e(url('admin/records.php')); ?>">
                                                <?php echo csrf_field(); ?><input type="hidden" name="op" value="media_delete"><input type="hidden" name="record_id" value="<?php echo $id; ?>"><input type="hidden" name="media_id" value="<?php echo (int) $item['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete this file permanently?">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="admin-empty">No files attached yet.</p>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo e(url('admin/records.php')); ?>" enctype="multipart/form-data" class="media-upload-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="op" value="upload">
                        <input type="hidden" name="record_id" value="<?php echo $id; ?>">
                        <label class="file-upload-zone file-upload-zone-sm" for="upload-media">
                            <span class="file-upload-title">📎 Add images or documents</span>
                            <span class="form-help">Up to <?php echo MAX_FILES_PER_UPLOAD; ?> files, <?php echo e(format_bytes((int) app_config('upload_max_bytes'))); ?> each</span>
                            <input type="file" id="upload-media" name="media[]" class="form-file-input" multiple accept="image/jpeg,image/png,image/gif,image/webp,application/pdf,text/plain,.docx">
                        </label>
                        <div class="file-preview-list" data-file-preview-for="upload-media"></div>
                        <div class="form-grid">
                            <input class="form-input" name="media_caption" maxlength="255" placeholder="Caption (optional)" aria-label="Caption">
                            <label class="form-check"><input type="checkbox" name="media_confidential" value="1"> <span>Confidential (staff only)</span></label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Upload Files</button>
                    </form>
                </div>
            </section>
        </div>

        <div>
            <section class="admin-card">
                <div class="admin-card-header"><h2>Publication Workflow</h2></div>
                <div class="admin-card-body">
                    <?php if ($record['status'] !== 'published' && $problems): ?>
                        <div class="form-alert form-alert-warning"><span>⚠</span><div><strong>Cannot publish yet:</strong><ul class="problem-list"><?php foreach ($problems as $problem): ?><li><?php echo e($problem); ?></li><?php endforeach; ?></ul></div></div>
                    <?php endif; ?>
                    <div class="workflow-buttons">
                        <?php
                        $transitions = $is_admin
                            ? ['published' => 'Approve & Publish', 'pending' => 'Send to Review', 'draft' => 'Return to Draft', 'archived' => 'Archive']
                            : ['pending' => 'Submit for Review', 'draft' => 'Return to Draft'];
                        foreach ($transitions as $target => $label):
                            if ($target === $record['status']) {
                                continue;
                            }
                            $disabled = $target === 'published' && $problems;
                        ?>
                            <form method="POST" action="<?php echo e(url('admin/records.php')); ?>">
                                <?php echo csrf_field(); ?><input type="hidden" name="op" value="set_status"><input type="hidden" name="record_id" value="<?php echo $id; ?>"><input type="hidden" name="status" value="<?php echo e($target); ?>">
                                <button type="submit" class="btn <?php echo $target === 'published' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm btn-block"<?php echo $disabled ? ' disabled' : ''; ?>><?php echo e($label); ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="admin-card">
                <div class="admin-card-header"><h2>Details</h2></div>
                <div class="admin-card-body">
                    <dl class="detail-list">
                        <dt>Category</dt><dd><?php echo e($record['category_name'] ?? '—'); ?></dd>
                        <dt>Community</dt><dd><?php echo e($record['community'] ?: '—'); ?></dd>
                        <dt>Language</dt><dd><?php echo e($record['language'] ?: '—'); ?></dd>
                        <dt>Source</dt><dd><?php echo e($record['source'] ?: '—'); ?></dd>
                        <dt>Period</dt><dd><?php echo e($record['period'] ?: '—'); ?></dd>
                        <dt>Date recorded</dt><dd><?php echo e(format_date($record['date_recorded'])); ?></dd>
                        <dt>Location</dt><dd><?php echo e($record['location'] ?: '—'); ?><?php echo $record['location'] && (int) $record['location_is_sensitive'] ? ' <span class="badge badge-warning">Hidden from public</span>' : ''; ?></dd>
                        <dt>Keywords</dt><dd><?php echo e($record['keywords'] ?: '—'); ?></dd>
                        <dt>Consent by</dt><dd><?php echo e($record['consent_given_by'] ?: '—'); ?><?php echo $record['consent_date'] ? ' (' . e(format_date($record['consent_date'])) . ')' : ''; ?></dd>
                        <dt>Consent notes</dt><dd><?php echo nl2br(e($record['consent_notes'] ?: '—')); ?></dd>
                        <dt>Created</dt><dd><?php echo e(format_date($record['created_at'], 'M j, Y g:i A')); ?><?php echo $record['created_by_name'] ? ' by ' . e($record['created_by_name']) : ''; ?></dd>
                        <dt>Updated</dt><dd><?php echo e(format_date($record['updated_at'], 'M j, Y g:i A')); ?><?php echo $record['updated_by_name'] ? ' by ' . e($record['updated_by_name']) : ''; ?></dd>
                        <dt>Approved</dt><dd><?php echo $record['approved_at'] ? e(format_date($record['approved_at'], 'M j, Y g:i A')) . ($record['approved_by_name'] ? ' by ' . e($record['approved_by_name']) : '') : '—'; ?></dd>
                    </dl>
                </div>
            </section>

            <?php if ($history): ?>
                <section class="admin-card">
                    <div class="admin-card-header"><h2>History</h2></div>
                    <ul class="history-list">
                        <?php foreach ($history as $entry): ?>
                            <li><strong><?php echo e(str_replace('_', ' ', $entry['action'])); ?></strong> <span><?php echo e($entry['username'] ?? 'system'); ?> &bull; <?php echo e(format_date($entry['created_at'], 'M j, Y g:i A')); ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if ($is_admin): ?>
                <section class="admin-card admin-card-danger">
                    <div class="admin-card-header"><h2>Danger Zone</h2></div>
                    <div class="admin-card-body">
                        <form method="POST" action="<?php echo e(url('admin/records.php')); ?>">
                            <?php echo csrf_field(); ?><input type="hidden" name="op" value="delete"><input type="hidden" name="record_id" value="<?php echo $id; ?>">
                            <button type="submit" class="btn btn-danger btn-block" data-confirm="Permanently delete “<?php echo e($record['title']); ?>” and all of its files? This cannot be undone.">Delete Record 🗑️</button>
                        </form>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>
    <?php
    require dirname(__DIR__) . '/includes/partials/admin_footer.php';
    exit;
}

// ----- List -----
$filters = [
    'q' => get_str('q', 100),
    'category_id' => get_int('category_id') ?: null,
    'status' => get_str('status', 20),
    'visibility' => get_str('visibility', 20),
    'consent_status' => get_str('consent_status', 20),
    'sort' => get_str('sort', 10) ?: 'updated',
];
$result = search_records($filters, get_int('page', 1), 15);
$filter_params = array_filter($filters, static fn($v) => $v !== null && $v !== '' && $v !== 'updated');

$admin_title = 'Heritage Records';
$admin_active = 'records';
require dirname(__DIR__) . '/includes/partials/admin_header.php';
?>
<div class="admin-header">
    <div>
        <h1>📚 Heritage Records</h1>
        <p class="admin-subtitle">Search, filter, and manage every record, including restricted and private ones.</p>
    </div>
    <a href="<?php echo e(url('admin/records.php', ['action' => 'add'])); ?>" class="btn btn-primary">+ Add Record</a>
</div>

<section class="admin-card">
    <form method="GET" action="<?php echo e(url('admin/records.php')); ?>" class="admin-filters">
        <input type="search" name="q" class="form-input" placeholder="Search title, description, community…" value="<?php echo e($filters['q']); ?>" aria-label="Search">
        <select name="category_id" class="form-select" aria-label="Category" data-autosubmit>
            <option value="">All categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo (int) $category['id']; ?>"<?php echo selected($category['id'], $filters['category_id']); ?>><?php echo e($category['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="form-select" aria-label="Status" data-autosubmit>
            <option value="">Any status</option>
            <?php foreach (RECORD_STATUSES as $value => $label): ?>
                <option value="<?php echo e($value); ?>"<?php echo selected($value, $filters['status']); ?>><?php echo e($label); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="visibility" class="form-select" aria-label="Visibility" data-autosubmit>
            <option value="">Any visibility</option>
            <?php foreach (VISIBILITY_LEVELS as $value => $label): ?>
                <option value="<?php echo e($value); ?>"<?php echo selected($value, $filters['visibility']); ?>><?php echo e(strtok($label, ' ')); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="consent_status" class="form-select" aria-label="Consent" data-autosubmit>
            <option value="">Any consent</option>
            <?php foreach (CONSENT_STATUSES as $value => $label): ?>
                <option value="<?php echo e($value); ?>"<?php echo selected($value, $filters['consent_status']); ?>><?php echo e($label); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="sort" class="form-select" aria-label="Sort" data-autosubmit>
            <option value="updated"<?php echo selected('updated', $filters['sort']); ?>>Recently updated</option>
            <option value="newest"<?php echo selected('newest', $filters['sort']); ?>>Newest</option>
            <option value="title"<?php echo selected('title', $filters['sort']); ?>>Title A–Z</option>
            <option value="oldest"<?php echo selected('oldest', $filters['sort']); ?>>Oldest</option>
        </select>
        <div class="admin-filter-actions">
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($filter_params): ?><a href="<?php echo e(url('admin/records.php')); ?>" class="btn btn-secondary">Reset</a><?php endif; ?>
        </div>
    </form>
</section>

<section class="admin-card">
    <div class="admin-card-header">
        <h2><?php echo $result['pager']['total']; ?> record<?php echo $result['pager']['total'] === 1 ? '' : 's'; ?></h2>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table admin-table-responsive">
            <thead>
                <tr><th>Media</th><th>Title</th><th>Category</th><th>Status</th><th>Visibility</th><th>Consent</th><th>Updated</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($result['rows'] as $row): ?>
                    <tr>
                        <td data-label="Media">
                            <?php if ($row['cover_media_id']): ?>
                                <img src="<?php echo e(media_url((int) $row['cover_media_id'], true)); ?>" alt="" class="table-thumb" loading="lazy">
                            <?php else: ?>
                                <span class="table-thumb table-thumb-fallback"><?php echo e($row['category_icon'] ?: '🌿'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Title">
                            <a class="table-title-link" href="<?php echo e(url('admin/records.php', ['action' => 'view', 'id' => $row['id']])); ?>"><?php echo e($row['title']); ?></a>
                            <div class="table-subtext"><?php echo e($row['community'] ?: str_limit($row['description'], 60)); ?></div>
                        </td>
                        <td data-label="Category"><?php echo e($row['category_name'] ?? '—'); ?></td>
                        <td data-label="Status"><?php echo status_badge($row['status']); ?></td>
                        <td data-label="Visibility"><?php echo visibility_badge($row['visibility']); ?><?php echo (int) $row['is_sacred'] ? ' <span class="badge badge-danger">Sacred</span>' : ''; ?></td>
                        <td data-label="Consent"><?php echo consent_badge($row['consent_status']); ?></td>
                        <td data-label="Updated"><?php echo e(format_date($row['updated_at'])); ?></td>
                        <td data-label="Actions">
                            <div class="table-actions">
                                <a href="<?php echo e(url('admin/records.php', ['action' => 'view', 'id' => $row['id']])); ?>" class="btn btn-secondary btn-sm">View</a>
                                <a href="<?php echo e(url('admin/records.php', ['action' => 'edit', 'id' => $row['id']])); ?>" class="btn btn-secondary btn-sm">Edit</a>
                                <?php if ($is_admin): ?>
                                    <form method="POST" action="<?php echo e(url('admin/records.php')); ?>">
                                        <?php echo csrf_field(); ?><input type="hidden" name="op" value="delete"><input type="hidden" name="record_id" value="<?php echo (int) $row['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" data-confirm="Permanently delete “<?php echo e($row['title']); ?>” and all of its files?">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$result['rows']): ?>
                    <tr><td colspan="8" class="admin-empty">No records match these filters.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="admin-card-body"><?php render_pagination($result['pager'], 'admin/records.php', $filter_params); ?></div>
</section>
<?php require dirname(__DIR__) . '/includes/partials/admin_footer.php'; ?>
