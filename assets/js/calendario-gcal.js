(function () {
    'use strict';

    var MESES = [
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
    ];
    var DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

    var HORA_MIN = 12;
    var HORA_MAX = 23;

    function pad(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function parseDateOnly(str) {
        if (!str) return null;
        var p = str.split('-');
        if (p.length !== 3) return null;
        return new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
    }

    function toDateKey(d) {
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    }

    function formatFechaLarga(dateStr) {
        var d = parseDateOnly(dateStr);
        if (!d) return dateStr;
        var dia = DIAS[d.getDay()];
        return dia.charAt(0).toUpperCase() + dia.slice(1) + ', ' + d.getDate() + ' de ' + MESES[d.getMonth()] + ' de ' + d.getFullYear();
    }

    function formatHora(fechaMysql) {
        if (!fechaMysql) return '';
        var m = fechaMysql.match(/(\d{2}):(\d{2})/);
        if (!m) return '';
        var h = parseInt(m[1], 10);
        var min = m[2];
        var suf = h >= 12 ? 'p. m.' : 'a. m.';
        var h12 = h % 12;
        if (h12 === 0) h12 = 12;
        return h12 + ':' + min + ' ' + suf;
    }

    function toH12(h24) {
        var h12 = h24 % 12;
        if (h12 === 0) h12 = 12;
        return h12;
    }

    function formatFechaEvento(fechaMysql) {
        var d = fechaMysql.split(' ')[0];
        return formatFechaLarga(d) + ' · ' + formatHora(fechaMysql);
    }

    function getCurrentUserId(root) {
        return parseInt(root.getAttribute('data-current-user') || '0', 10);
    }

    function getEventClass(ev, currentUserId) {
        if (ev.estado === 'pendiente') return 'pendiente';
        var esPropia = (parseInt(ev.usuario_id, 10) === currentUserId);
        return esPropia ? 'aprobada' : 'ocupado';
    }

    function renderHourOptions(hourSelect) {
        if (!hourSelect) return;
        hourSelect.innerHTML = '';
        for (var i = 1; i <= 12; i++) {
            var h24 = i === 12 ? 12 : i + 12;
            var opt = document.createElement('option');
            opt.value = pad(h24);
            opt.textContent = String(i);
            hourSelect.appendChild(opt);
        }
    }

    function init() {
        var root = document.querySelector('.gcal');
        if (!root) return;

        var grid = root.querySelector('[data-gcal-grid]');
        var titleEl = root.querySelector('[data-gcal-title]');
        if (!grid || !titleEl) return;

        var events = [];
        try {
            events = JSON.parse(root.getAttribute('data-events') || '[]');
        } catch (e) {
            events = [];
        }

        var currentUserId = getCurrentUserId(root);
        var hoyKey = root.getAttribute('data-hoy') || '';
        var minKey = root.getAttribute('data-min') || '';
        var maxKey = root.getAttribute('data-max') || '';
        var canReserve = root.getAttribute('data-can-reserve') === '1';

        var viewYear, viewMonth;
        var hoyDate = parseDateOnly(hoyKey) || new Date();
        viewYear = hoyDate.getFullYear();
        viewMonth = hoyDate.getMonth();

        var eventsByDay = {};
        events.forEach(function (ev) {
            var day = (ev.fecha || '').split(' ')[0];
            if (!day) return;
            if (!eventsByDay[day]) eventsByDay[day] = [];
            eventsByDay[day].push(ev);
        });

        var detailModal = document.getElementById('gcal-event-detail');
        var reserveModal = document.getElementById('gcal-reserva-modal');
        var fechaHidden = document.getElementById('gcal-fecha-hidden');
        var dateLabel = document.querySelector('[data-gcal-date-label]');
        var hourSelect = document.querySelector('[data-gcal-hour]');
        var minuteSelect = document.querySelector('[data-gcal-minute]');
        var formError = document.querySelector('[data-gcal-form-error]');

        renderHourOptions(hourSelect);

        if (minuteSelect) {
            minuteSelect.innerHTML = '';
            for (var mi = 0; mi < 60; mi += 5) {
                var o = document.createElement('option');
                o.value = pad(mi);
                o.textContent = pad(mi);
                minuteSelect.appendChild(o);
            }
        }

        var errorAttr = root.getAttribute('data-error');
        if (errorAttr && formError) {
            formError.textContent = errorAttr;
            formError.hidden = false;
        }

        function isInRange(dateKey) {
            if (minKey && dateKey < minKey) return false;
            if (maxKey && dateKey > maxKey) return false;
            return true;
        }

        function syncHiddenDate(dateKey) {
            if (!fechaHidden || !hourSelect || !minuteSelect) return;
            fechaHidden.value = dateKey + 'T' + hourSelect.value + ':' + minuteSelect.value;
            if (dateLabel) dateLabel.textContent = formatFechaLarga(dateKey);
        }

        function openPopover(modal) {
            if (!modal) return;
            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
            modal.hidden = false;
            document.body.classList.add('gcal-modal-open');
        }

        function closePopover(modal) {
            if (!modal) return;
            modal.hidden = true;
            if (!document.querySelector('.gcal-popover:not([hidden])')) {
                document.body.classList.remove('gcal-modal-open');
            }
        }

        function closeAllPopovers() {
            document.querySelectorAll('.gcal-popover').forEach(function (m) {
                m.hidden = true;
            });
            document.body.classList.remove('gcal-modal-open');
        }

        function showEventDetail(ev) {
            if (!detailModal) return;
            var bar = detailModal.querySelector('[data-gcal-detail-bar]');
            var tit = detailModal.querySelector('[data-gcal-detail-title]');
            var fec = detailModal.querySelector('[data-gcal-detail-fecha]');
            var est = detailModal.querySelector('[data-gcal-detail-estado]');
            var desc = detailModal.querySelector('[data-gcal-detail-desc]');
            var evClass = getEventClass(ev, currentUserId);
            var estado = evClass === 'aprobada' ? 'Aprobada' : (evClass === 'pendiente' ? 'Pendiente' : 'Ocupado');
            var barClass = evClass;
            if (bar) {
                bar.className = 'gcal-popover-bar gcal-popover-bar--' + barClass;
            }
            if (tit) tit.textContent = ev.titulo || 'Reserva del salón';
            if (fec) fec.textContent = formatFechaEvento(ev.fecha || '');
            if (est) {
                est.textContent = estado;
                est.className = 'gcal-popover-estado gcal-estado-' + evClass;
            }
            if (desc) desc.textContent = ev.descripcion || 'Sin descripción adicional.';
            openPopover(detailModal);
        }

        function openReserve(dateKey) {
            if (!canReserve || !reserveModal) return;
            if (!isInRange(dateKey)) return;
            if (formError) {
                formError.textContent = '';
                formError.hidden = true;
            }
            if (hourSelect) hourSelect.value = '18';
            if (minuteSelect) minuteSelect.value = '00';
            syncHiddenDate(dateKey);
            openPopover(reserveModal);
        }

        function render() {
            titleEl.textContent = MESES[viewMonth].charAt(0).toUpperCase() + MESES[viewMonth].slice(1) + ' de ' + viewYear;
            grid.innerHTML = '';

            var first = new Date(viewYear, viewMonth, 1);
            var startPad = (first.getDay() + 6) % 7;
            var daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
            var prevMonthDays = new Date(viewYear, viewMonth, 0).getDate();

            var totalCells = Math.ceil((startPad + daysInMonth) / 7) * 7;
            if (totalCells < 42) totalCells = 42;

            for (var i = 0; i < totalCells; i++) {
                var dayNum, cellMonth, cellYear, otherMonth = false;

                if (i < startPad) {
                    dayNum = prevMonthDays - startPad + i + 1;
                    cellMonth = viewMonth - 1;
                    cellYear = viewYear;
                    if (cellMonth < 0) {
                        cellMonth = 11;
                        cellYear--;
                    }
                    otherMonth = true;
                } else if (i >= startPad + daysInMonth) {
                    dayNum = i - startPad - daysInMonth + 1;
                    cellMonth = viewMonth + 1;
                    cellYear = viewYear;
                    if (cellMonth > 11) {
                        cellMonth = 0;
                        cellYear++;
                    }
                    otherMonth = true;
                } else {
                    dayNum = i - startPad + 1;
                    cellMonth = viewMonth;
                    cellYear = viewYear;
                }

                var cellDate = new Date(cellYear, cellMonth, dayNum);
                var dateKey = toDateKey(cellDate);
                var isToday = dateKey === hoyKey;
                var inRange = isInRange(dateKey);
                var dayEvents = eventsByDay[dateKey] || [];

                var isOwnApproved = false;
                var isOwnApproved = false;
                var isPending = false;
                var otherApproved = false;
                dayEvents.forEach(function (ev) {
                    if (ev.estado === 'pendiente') {
                        isPending = true;
                    } else if (ev.estado === 'aprobada') {
                        var esPropia = (parseInt(ev.usuario_id, 10) === currentUserId);
                        if (esPropia) {
                            isOwnApproved = true;
                        } else {
                            otherApproved = true;
                        }
                    }
                });

                var cell = document.createElement('div');
                cell.className = 'gcal-day';
                cell.setAttribute('role', 'gridcell');
                if (otherMonth) cell.classList.add('is-other-month');
                if (isToday) cell.classList.add('is-today');
                if (!inRange) cell.classList.add('is-disabled');

                if (isPending) {
                    cell.classList.add('has-pendiente-propia');
                } else if (isOwnApproved) {
                    cell.classList.add('has-aprobada-propia');
                } else if (otherApproved) {
                    cell.classList.add('has-ocupado');
                } else if (dayEvents.length > 0) {
                    cell.classList.add('has-ocupado');
                }

                cell.dataset.date = dateKey;

                var num = document.createElement('span');
                num.className = 'gcal-day-num';
                num.textContent = String(dayNum);
                cell.appendChild(num);

                var evWrap = document.createElement('div');
                evWrap.className = 'gcal-day-events';
                dayEvents.slice(0, 3).forEach(function (ev) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    var evClass = getEventClass(ev, currentUserId);
                    btn.className = 'gcal-event gcal-event--' + evClass;
                    btn.textContent = formatHora(ev.fecha) + ' ' + (ev.titulo || 'Reserva');
                    btn.addEventListener('click', function (e) {
                        e.stopPropagation();
                        showEventDetail(ev);
                    });
                    evWrap.appendChild(btn);
                });
                if (dayEvents.length > 3) {
                    var more = document.createElement('span');
                    more.className = 'gcal-more';
                    more.textContent = '+' + (dayEvents.length - 3) + ' más';
                    evWrap.appendChild(more);
                }
                cell.appendChild(evWrap);

                if (canReserve && inRange && !otherMonth && dayEvents.length === 0) {
                    cell.classList.add('is-clickable');
                    cell.addEventListener('click', function (dk) {
                        return function () {
                            openReserve(dk);
                        };
                    }(dateKey));
                } else if (dayEvents.length > 0) {
                    cell.classList.add('is-clickable');
                    cell.addEventListener('click', function (list) {
                        return function () {
                            if (list.length === 1) showEventDetail(list[0]);
                            else if (list.length > 1) {
                                showEventDetail(list[0]);
                            }
                        };
                    }(dayEvents));
                }

                grid.appendChild(cell);
            }
        }

        root.querySelector('[data-gcal-prev]')?.addEventListener('click', function () {
            viewMonth--;
            if (viewMonth < 0) {
                viewMonth = 11;
                viewYear--;
            }
            render();
        });

        root.querySelector('[data-gcal-next]')?.addEventListener('click', function () {
            viewMonth++;
            if (viewMonth > 11) {
                viewMonth = 0;
                viewYear++;
            }
            render();
        });

        root.querySelector('[data-gcal-today]')?.addEventListener('click', function () {
            viewYear = hoyDate.getFullYear();
            viewMonth = hoyDate.getMonth();
            render();
        });

        root.querySelector('[data-gcal-create]')?.addEventListener('click', function () {
            if (isInRange(hoyKey)) {
                openReserve(hoyKey);
            } else if (minKey) {
                openReserve(minKey);
            }
        });

        document.querySelectorAll('[data-gcal-close]').forEach(function (btn) {
            btn.addEventListener('click', closeAllPopovers);
        });

        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') closeAllPopovers();
        });

        if (hourSelect) hourSelect.addEventListener('change', function () {
            var dk = (fechaHidden && fechaHidden.value.split('T')[0]) || hoyKey;
            syncHiddenDate(dk);
        });
        if (minuteSelect) minuteSelect.addEventListener('change', function () {
            var dk = (fechaHidden && fechaHidden.value.split('T')[0]) || hoyKey;
            syncHiddenDate(dk);
        });

        var form = document.getElementById('gcal-reserva-form');
        if (form) {
            form.addEventListener('submit', function () {
                var dk = (fechaHidden && fechaHidden.value.split('T')[0]) || hoyKey;
                syncHiddenDate(dk);
            });
        }

        render();

        var openOnLoad = root.getAttribute('data-open-reserve');
        if (openOnLoad === '1' && canReserve) {
            openReserve(hoyKey);
        }

        var reopenDate = root.getAttribute('data-reopen-date');
        if (reopenDate && canReserve) {
            var rd = parseDateOnly(reopenDate);
            if (rd) {
                viewYear = rd.getFullYear();
                viewMonth = rd.getMonth();
                render();
            }
            openReserve(reopenDate);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
