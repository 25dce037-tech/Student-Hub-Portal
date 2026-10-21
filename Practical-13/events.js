const eventForm = document.getElementById("event-form");
const eventList = document.getElementById("event-list");
const eventMessage = document.getElementById("event-message");
const eventToken = document.querySelector('meta[name="csrf-token"]').content;
const eventApi = "../php/events_api.php";
const posterBase = "../uploads/event-posters/";

function showEventMessage(text, type) {
    eventMessage.textContent = text;
    eventMessage.className = `student-message ${type}`;
    eventMessage.hidden = false;
}

async function sendEventRequest(url, options) {
    const response = await fetch(url, options);
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || "Event request failed.");
    return data;
}

function makeCell(row, text) {
    const cell = document.createElement("td");
    cell.textContent = text;
    row.appendChild(cell);
    return cell;
}

async function loadEvents() {
    eventList.replaceChildren();
    try {
        const data = await sendEventRequest(eventApi, { credentials: "same-origin" });
        if (!data.events.length) {
            const row = document.createElement("tr");
            makeCell(row, "No events found.");
            row.firstChild.colSpan = 5;
            eventList.appendChild(row);
            return;
        }
        data.events.forEach((event) => {
            const row = document.createElement("tr");
            const posterCell = document.createElement("td");
            if (event.poster_path) {
                const image = document.createElement("img");
                image.className = "event-poster";
                image.src = posterBase + encodeURIComponent(event.poster_path);
                image.alt = `Poster for ${event.title}`;
                posterCell.appendChild(image);
            } else {
                posterCell.textContent = "—";
            }
            row.appendChild(posterCell);
            makeCell(row, event.title);
            makeCell(row, event.event_date);
            makeCell(row, event.location);

            const actions = document.createElement("td");
            const actionGroup = document.createElement("div");
            actionGroup.className = "student-actions";
            const editButton = document.createElement("button");
            editButton.className = "btn";
            editButton.type = "button";
            editButton.textContent = "Edit";
            editButton.addEventListener("click", () => editEvent(event));
            const deleteButton = document.createElement("button");
            deleteButton.className = "btn";
            deleteButton.type = "button";
            deleteButton.textContent = "Delete";
            deleteButton.addEventListener("click", () => deleteEvent(event.id));
            actionGroup.append(editButton, deleteButton);
            actions.appendChild(actionGroup);
            row.appendChild(actions);
            eventList.appendChild(row);
        });
    } catch (error) {
        eventList.replaceChildren();
        const row = document.createElement("tr");
        const cell = makeCell(row, error.message);
        cell.colSpan = 5;
        eventList.appendChild(row);
        showEventMessage(error.message, "error");
    }
}

function editEvent(event) {
    eventForm.elements.id.value = event.id;
    eventForm.elements.title.value = event.title;
    eventForm.elements.event_date.value = event.event_date;
    eventForm.elements.location.value = event.location;
    eventForm.elements.poster.value = "";
    document.getElementById("event-form-title").textContent = "Edit Event";
    document.getElementById("event-submit").textContent = "Save Event";
    document.getElementById("event-cancel").hidden = false;
    eventForm.scrollIntoView({ behavior: "smooth", block: "start" });
}

function resetEventForm() {
    eventForm.reset();
    eventForm.elements.id.value = "";
    document.getElementById("event-form-title").textContent = "Add Event";
    document.getElementById("event-submit").textContent = "Add Event";
    document.getElementById("event-cancel").hidden = true;
}

async function deleteEvent(id) {
    if (!window.confirm("Delete this event?")) return;
    const formData = new FormData();
    formData.set("action", "delete");
    formData.set("id", id);
    try {
        const result = await sendEventRequest(eventApi, {
            method: "POST",
            credentials: "same-origin",
            headers: { "X-CSRF-Token": eventToken },
            body: formData
        });
        showEventMessage(result.message, "success");
        await loadEvents();
    } catch (error) {
        showEventMessage(error.message, "error");
    }
}

eventForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    const formData = new FormData(eventForm);
    formData.set("action", formData.get("id") ? "update" : "add");
    try {
        const result = await sendEventRequest(eventApi, {
            method: "POST",
            credentials: "same-origin",
            headers: { "X-CSRF-Token": eventToken },
            body: formData
        });
        showEventMessage(result.message, "success");
        resetEventForm();
        await loadEvents();
    } catch (error) {
        showEventMessage(error.message, "error");
    }
});

document.getElementById("event-cancel").addEventListener("click", resetEventForm);
loadEvents();
