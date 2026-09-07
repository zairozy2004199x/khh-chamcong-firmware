import React from "react";
import { createRoot } from "react-dom/client";
import { App, ZMPRouter, AnimationRoutes, SnackbarProvider, Route } from "zmp-ui";
import HomePage from "./pages/home";
import BuyPage from "./pages/buy";
import TicketPage from "./pages/ticket";
import "./css/app.css";

const Layout = () =>
  React.createElement(
    App,
    null,
    React.createElement(
      SnackbarProvider,
      null,
      React.createElement(
        ZMPRouter,
        null,
        React.createElement(
          AnimationRoutes,
          null,
          React.createElement(Route, { path: "/", element: React.createElement(HomePage) }),
          React.createElement(Route, { path: "/buy/:id", element: React.createElement(BuyPage) }),
          React.createElement(Route, { path: "/ticket/:maVe", element: React.createElement(TicketPage) })
        )
      )
    )
  );

const root = createRoot(document.getElementById("app")!);
root.render(React.createElement(Layout));
