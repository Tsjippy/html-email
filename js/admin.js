function showdatefields(target) {
  document.getElementById("querydates").style.display = "none";
  document
    .getElementById("querydates")
    .querySelectorAll("input")
    .forEach((el) => (el.value = ""));

  target.closest("div").querySelector('[name="date"]').style.display = "none";
  target.closest("div").querySelector('[name="date"]').value = "";

  if (target.value == "after") {
    target.closest("div").querySelector('[name="date"]').style.display = "";
  } else if (target.value == "custom") {
    document.getElementById("querydates").style.display = "";
  }
}
