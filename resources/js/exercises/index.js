import "./exercises.css";
import { initExercises } from "./engine.js";

if (document.readyState !== "loading") initExercises();
else document.addEventListener("DOMContentLoaded", initExercises, { once: true });

export { RENDERERS, initExercises } from "./engine.js";
