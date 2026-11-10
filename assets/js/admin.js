/**
 * Customer Tagger admin workflow editor.
 */
(function () {
	'use strict';

	if (typeof ctAdmin === 'undefined') {
		return;
	}

	var root = ctAdmin.root.replace(/\/$/, '');
	var headers = {
		'Content-Type': 'application/json',
		'X-WP-Nonce': ctAdmin.nonce,
	};

	function api(path, options) {
		options = options || {};
		return fetch(root + path, Object.assign({ headers: headers }, options)).then(function (r) {
			if (!r.ok) {
				return r.json().then(function (e) {
					throw new Error((e && e.message) || 'Request failed');
				});
			}
			return r.json();
		});
	}

	function el(tag, attrs, children) {
		var node = document.createElement(tag);
		if (attrs) {
			Object.keys(attrs).forEach(function (k) {
				if (k === 'className') {
					node.className = attrs[k];
				} else if (k === 'text') {
					node.textContent = attrs[k];
				} else {
					node.setAttribute(k, attrs[k]);
				}
			});
		}
		(children || []).forEach(function (child) {
			if (typeof child === 'string') {
				node.appendChild(document.createTextNode(child));
			} else if (child) {
				node.appendChild(child);
			}
		});
		return node;
	}

	function formRow(label, control, description) {
		var tr = el('tr');
		tr.appendChild(el('th', { scope: 'row' }, [el('label', { text: label })]));
		var td = el('td');
		td.appendChild(control);
		if (description) {
			td.appendChild(el('p', { className: 'description', text: description }));
		}
		tr.appendChild(td);
		return tr;
	}

	function editorCard(title, bodyChildren) {
		return el('div', { className: 'ct-editor-card' }, [
			el('h2', { className: 'ct-editor-card__title', text: title }),
			el('div', { className: 'ct-editor-card__body' }, bodyChildren),
		]);
	}

	function renderWorkflowEditor(container) {
		var workflowId = parseInt(container.getAttribute('data-workflow-id') || '0', 10);
		var state = {
			title: 'New workflow',
			active: true,
			entity: 'customer',
			trigger: 'order_placed',
			priority: 10,
			delay_seconds: 0,
			config: {
				version: 1,
				conditions: { op: 'AND', rules: [] },
				actions: [{ type: 'add_tags', tags: [] }],
				lifecycle: {},
			},
		};

		var fields = [];

		function loadFields() {
			return api('/fields?entity=' + encodeURIComponent(state.entity)).then(function (data) {
				fields = data;
			});
		}

		function loadWorkflow() {
			if (!workflowId) {
				return Promise.resolve();
			}
			return api('/workflows/' + workflowId).then(function (data) {
				state.title = data.title;
				state.active = data.active;
				state.entity = data.entity || 'customer';
				state.trigger = data.trigger || 'order_placed';
				state.config = data.config || state.config;
				if (data.meta) {
					state.priority = data.meta.priority || 10;
					state.delay_seconds = data.meta.delay_seconds || 0;
				}
			});
		}

		function saveWorkflow() {
			var payload = {
				title: state.title,
				active: state.active,
				entity: state.entity,
				trigger: state.trigger,
				config: state.config,
				meta: {
					priority: state.priority,
					delay_seconds: state.delay_seconds,
					run_mode: 'realtime',
					entity: state.entity,
					trigger: state.trigger,
					active: state.active,
				},
			};

			var req = workflowId
				? api('/workflows/' + workflowId, { method: 'PUT', body: JSON.stringify(payload) })
				: api('/workflows', { method: 'POST', body: JSON.stringify(payload) });

			return req.then(function (res) {
				if (!workflowId && res.id) {
					workflowId = res.id;
					window.location.search = '?page=customer-tagger-workflow&workflow_id=' + workflowId;
				}
				alert('Workflow saved.');
			}).catch(function (err) {
				alert(err.message);
			});
		}

		function previewWorkflow() {
			var orderId = parseInt(prompt('Order ID to test against:') || '0', 10);
			if (!orderId || !workflowId) {
				return;
			}
			api('/workflows/' + workflowId + '/preview', {
				method: 'POST',
				body: JSON.stringify({ order_id: orderId }),
			}).then(function (res) {
				alert(res.matches ? 'Conditions MATCH' : 'Conditions do not match');
			});
		}

		function redraw() {
			var titleInput = el('input', { type: 'text', className: 'regular-text', value: state.title });
			titleInput.addEventListener('change', function () {
				state.title = titleInput.value;
			});

			var entitySelect = el('select', { className: 'ct-editor-select' });
			['customer', 'order', 'product'].forEach(function (v) {
				var o = el('option', { value: v, text: v });
				if (v === state.entity) {
					o.selected = true;
				}
				entitySelect.appendChild(o);
			});
			entitySelect.addEventListener('change', function () {
				state.entity = entitySelect.value;
				loadFields().then(redraw);
			});

			var triggerSelect = el('select', { className: 'ct-editor-select' });
			[
				'order_placed',
				'order_paid',
				'order_status_changed',
				'customer_registered',
				'scheduled_daily',
				'product_saved',
				'product_stock_changed',
			].forEach(function (v) {
				var o = el('option', { value: v, text: v });
				if (v === state.trigger) {
					o.selected = true;
				}
				triggerSelect.appendChild(o);
			});
			triggerSelect.addEventListener('change', function () {
				state.trigger = triggerSelect.value;
			});

			var activeCheck = el('input', { type: 'checkbox', id: 'ct-workflow-active' });
			activeCheck.checked = state.active;
			activeCheck.addEventListener('change', function () {
				state.active = activeCheck.checked;
			});
			var activeLabel = el('label', { for: 'ct-workflow-active', text: 'Run this workflow when the trigger fires' });

			var tags = (state.config.actions[0] && state.config.actions[0].tags) || [];
			var tagsInput = el('input', { type: 'text', className: 'large-text', value: tags.join(', ') });
			tagsInput.addEventListener('change', function () {
				state.config.actions = [
					{
						type: 'add_tags',
						tags: tagsInput.value.split(',').map(function (t) {
							return t.trim();
						}).filter(Boolean),
					},
				];
			});

			var dynamicInput = el('input', {
				type: 'text',
				className: 'large-text',
				placeholder: 'used-coupon-{order.coupon_code}',
			});
			dynamicInput.addEventListener('change', function () {
				if (!dynamicInput.value) {
					return;
				}
				state.config.actions.push({ type: 'add_dynamic_tag', template: dynamicInput.value });
			});

			var rulesRoot = el('div', { className: 'ct-rules', id: 'ct-rules-root' });
			var addConditionBtn = el('button', {
				type: 'button',
				className: 'button ct-add-condition',
				text: 'Add condition',
			});

			var saveBtn = el('button', { type: 'button', className: 'button button-primary', text: 'Save workflow' });
			var previewBtn = el('button', { type: 'button', className: 'button', text: 'Preview' });

			container.innerHTML = '';
			container.appendChild(
				el('div', { className: 'ct-editor' }, [
					editorCard('Workflow settings', [
						el('table', { className: 'form-table', role: 'presentation' }, [
							formRow('Title', titleInput),
							formRow('Entity', entitySelect, 'What this workflow evaluates and tags.'),
							formRow('Trigger', triggerSelect, 'When the workflow should run.'),
							formRow('Active', el('span', { className: 'ct-checkbox-field' }, [activeCheck, ' ', activeLabel])),
						]),
					]),
					editorCard('Conditions', [
						el('p', { className: 'description ct-editor-intro', text: 'All conditions must match (AND). Leave empty to run for every trigger event.' }),
						rulesRoot,
						addConditionBtn,
					]),
					editorCard('Actions', [
						el('table', { className: 'form-table', role: 'presentation' }, [
							formRow('Tags', tagsInput, 'Comma-separated tag slugs to add when conditions match.'),
							formRow('Dynamic tag', dynamicInput, 'Optional template with placeholders, e.g. used-coupon-{order.coupon_code}.'),
						]),
					]),
					el('p', { className: 'submit ct-editor-submit' }, [saveBtn, previewBtn]),
				])
			);

			(state.config.conditions.rules || []).forEach(function (rule, idx) {
				rulesRoot.appendChild(renderRuleRow(rule, idx));
			});

			addConditionBtn.addEventListener('click', function () {
				state.config.conditions.rules.push({ field: 'order.total', operator: 'gte', value: 0 });
				redraw();
			});

			saveBtn.addEventListener('click', saveWorkflow);
			previewBtn.addEventListener('click', previewWorkflow);
		}

		function renderRuleRow(rule, idx) {
			var row = el('div', { className: 'ct-rule-row' });
			var fieldSelect = el('select');
			fields.forEach(function (f) {
				var opt = el('option', { value: f.field, text: f.label });
				if (f.field === rule.field) {
					opt.selected = true;
				}
				fieldSelect.appendChild(opt);
			});
			fieldSelect.addEventListener('change', function () {
				state.config.conditions.rules[idx].field = fieldSelect.value;
			});

			var op = el('select');
			['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'contains'].forEach(function (o) {
				var opt = el('option', { value: o, text: o });
				if (o === rule.operator) {
					opt.selected = true;
				}
				op.appendChild(opt);
			});
			op.addEventListener('change', function () {
				state.config.conditions.rules[idx].operator = op.value;
			});

			var val = el('input', { type: 'text', value: rule.value != null ? String(rule.value) : '' });
			val.addEventListener('change', function () {
				state.config.conditions.rules[idx].value = val.value;
			});

			row.appendChild(fieldSelect);
			row.appendChild(op);
			row.appendChild(val);
			return row;
		}

		loadFields().then(loadWorkflow).then(redraw);
	}

	function renderTools(container) {
		container.appendChild(el('h2', { text: 'Backfill historical orders' }));
		var after = el('input', { type: 'date' });
		var before = el('input', { type: 'date' });
		var btn = el('button', { type: 'button', className: 'button button-primary', text: 'Queue backfill' });
		btn.addEventListener('click', function () {
			api('/backfill', {
				method: 'POST',
				body: JSON.stringify({ after: after.value, before: before.value }),
			})
				.then(function () {
					alert('Backfill queued.');
				})
				.catch(function (e) {
					alert(e.message);
				});
		});
		container.appendChild(el('p', null, [el('label', { text: 'After ' }), after, el('label', { text: ' Before ' }), before, btn]));

		container.appendChild(el('h2', { text: 'Export customers by tag' }));
		var tagInput = el('input', { type: 'text', placeholder: 'tag-slug' });
		var exportBtn = el('button', { type: 'button', className: 'button', text: 'Export JSON' });
		exportBtn.addEventListener('click', function () {
			api('/export?tag=' + encodeURIComponent(tagInput.value))
				.then(function (data) {
					var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
					var a = document.createElement('a');
					a.href = URL.createObjectURL(blob);
					a.download = 'customers-' + tagInput.value + '.json';
					a.click();
				})
				.catch(function (e) {
					alert(e.message);
				});
		});
		container.appendChild(el('p', null, [tagInput, exportBtn]));
	}

	document.addEventListener('DOMContentLoaded', function () {
		var editor = document.getElementById('ct-workflow-editor');
		if (editor) {
			renderWorkflowEditor(editor);
		}
		var tools = document.getElementById('ct-tools-app');
		if (tools) {
			renderTools(tools);
		}
	});
})();
